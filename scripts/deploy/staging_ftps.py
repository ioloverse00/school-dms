"""Deploy code changes since the initial staging release; preserve runtime data.

Dependency/schema changes and unapproved deletions require a separate release.
No credentials or server responses are logged.
"""
import ftplib
import hashlib
import io
import os
from pathlib import Path, PurePosixPath
import ssl
import subprocess
import sys
import uuid
import urllib.request

BASE = '78db72aaade1fa217898ef9ade01b3e7dd902647'
CODE_PREFIXES = ('app/', 'resources/views/', 'config/', 'routes/', 'public/assets/')
APPROVED_DELETIONS = {'app/Services/GeminiClient.php'}


def destination(source):
    path = PurePosixPath(source)
    if '..' in path.parts or path.is_absolute() or '\\' in source:
        raise ValueError('Unsafe source path')
    if source.startswith(('app/', 'resources/views/', 'config/', 'routes/')):
        return 'dms-staging-app/' + source
    if source.startswith('public/assets/'):
        return 'public_html/staging/' + source.removeprefix('public/')
    raise ValueError('Path outside the code patch scope')


def payload():
    raw = subprocess.check_output(['git', 'diff', '--name-status', '--no-renames', '-z', BASE, 'HEAD'])
    fields = raw.decode().rstrip('\0').split('\0')
    result = []
    for status, source in zip(fields[::2], fields[1::2]):
        if source in ('composer.json', 'composer.lock') or source.startswith('database/migrations/'):
            raise ValueError('Dependency/schema changes require a separate release')
        if not source.startswith(CODE_PREFIXES):
            continue
        if status == 'D' and source in APPROVED_DELETIONS:
            result.append((None, destination(source)))
            continue
        if status not in ('A', 'M'):
            raise ValueError('Code deletions require a separate release')
        result.append((source, destination(source)))
    if not result:
        raise ValueError('No deployable code changes')
    return result


def names(ftp):
    return {name.rstrip('/').rsplit('/', 1)[-1] for name in ftp.nlst()}


def domain_root(ftp):
    start = ftp.pwd()
    for relative in ('.', '..', '/', 'domains/school-dms.online', 'school-dms.online'):
        try:
            ftp.cwd(start)
            ftp.cwd(relative)
            if {'dms-staging-app', 'public_html'}.issubset(names(ftp)):
                root = ftp.pwd().rstrip('/')
                ftp.cwd(root + '/dms-staging-app')
                if not {'artisan', 'app', 'vendor'}.issubset(names(ftp)):
                    continue
                ftp.cwd(root + '/public_html/staging')
                if {'index.php', 'assets'}.issubset(names(ftp)):
                    return root
        except ftplib.error_perm:
            pass
    raise ValueError('Expected staging folders not found')


def mkdirs(ftp, path):
    current = ''
    for part in PurePosixPath(path).parts:
        if part == '/':
            continue
        current += '/' + part
        try:
            ftp.cwd(current)
        except ftplib.error_perm:
            ftp.mkd(current)


def digest_remote(ftp, path):
    digest = hashlib.sha256()
    ftp.retrbinary('RETR ' + path, digest.update)
    return digest.hexdigest()


def deploy(ftp, files, root, release, verify=None):
    staged = []
    activated = []
    backup = root + '/dms-staging-app/storage/deploy-backups/' + release
    try:
        # Upload and verify every temporary file before replacing any live file.
        for source, target in files:
            if source is None:
                staged.append(None)
                continue
            final = root + '/' + target
            temp = final + '.' + release + '.tmp'
            mkdirs(ftp, str(PurePosixPath(final).parent))
            data = Path(source).read_bytes()
            staged.append(temp)
            ftp.storbinary('STOR ' + temp, io.BytesIO(data))
            if digest_remote(ftp, temp) != hashlib.sha256(data).hexdigest():
                raise ValueError('Uploaded file verification failed')
        for (source, target), temp in zip(files, staged):
            final = root + '/' + target
            old = backup + '/' + target
            mkdirs(ftp, str(PurePosixPath(old).parent))
            ftp.cwd(str(PurePosixPath(final).parent))
            existed = PurePosixPath(final).name in names(ftp)
            if existed:
                ftp.rename(final, old)
            activated.append((final, old, existed))
            if temp is not None:
                ftp.rename(temp, final)
        # Retire generated config/route caches so this release loads new code.
        # Keep them in the rollback backup, with the same restoration handling.
        for filename in ('config.php', 'routes-v7.php'):
            final = root + '/dms-staging-app/bootstrap/cache/' + filename
            try:
                ftp.cwd(str(PurePosixPath(final).parent))
            except ftplib.error_perm:
                continue
            if filename in names(ftp):
                old = backup + '/runtime-cache/' + filename
                mkdirs(ftp, str(PurePosixPath(old).parent))
                ftp.rename(final, old)
                activated.append((final, old, True))
        if verify:
            verify()
        print('Deployed', len(files), 'verified code files.')
        print('Private rollback backup:', 'storage/deploy-backups/' + release)
        print('No environment files, uploads, databases, dependencies or migrations changed.')
    except Exception:
        restored = True
        for final, old, existed in reversed(activated):
            try:
                ftp.cwd(str(PurePosixPath(final).parent))
                if PurePosixPath(final).name in names(ftp):
                    ftp.delete(final)
                if existed:
                    ftp.rename(old, final)
            except Exception:
                restored = False
        print('Rollback completed.' if restored else 'Rollback incomplete; restore from the private backup before retrying.')
        raise
    finally:
        for temp in staged:
            if temp is None:
                continue
            try:
                ftp.delete(temp)
            except Exception:
                pass


def main():
    files = payload()
    if not all(os.environ.get(key) for key in ('FTP_HOST', 'FTP_USER', 'FTP_PASSWORD')):
        raise ValueError('Missing FTP secrets')
    release = subprocess.check_output(['git', 'rev-parse', '--short', 'HEAD']).decode().strip() + '-' + uuid.uuid4().hex[:8]
    # New asset URLs on every release prevent browsers retaining old CSS/JS.
    Path('config/assets.php').write_text("<?php\nreturn ['version' => '" + release + "'];\n", encoding='utf-8')
    with ftplib.FTP_TLS(context=ssl.create_default_context(), timeout=60) as ftp:
        ftp.connect(os.environ['FTP_HOST'], 21)
        ftp.auth()
        ftp.login(os.environ['FTP_USER'], os.environ['FTP_PASSWORD'])
        ftp.prot_p()
        root = domain_root(ftp)
        def verify():
            with urllib.request.urlopen('https://staging.school-dms.online/login', timeout=30) as response:
                if response.status != 200 or not response.url.startswith('https://staging.school-dms.online/'):
                    raise ValueError('Staging login health check failed')
                body = response.read(200000)
                if b'Sign in' not in body:
                    raise ValueError('Expected login form not found')
                if ('favicon.svg?v=' + release).encode() not in body:
                    raise ValueError('Staging asset version verification failed')
            with urllib.request.urlopen('https://staging.school-dms.online/assets/images/favicon.svg', timeout=30) as response:
                if response.read(100000) != Path('public/assets/images/favicon.svg').read_bytes():
                    raise ValueError('Staging favicon verification failed')
            with urllib.request.urlopen('https://staging.school-dms.online/notifications', timeout=30) as response:
                if response.status != 200 or response.url != 'https://staging.school-dms.online/login':
                    raise ValueError('Notifications authentication check failed')
            print('Staging HTTPS login check passed.')
        deploy(ftp, files, root, release, verify)


if __name__ == '__main__':
    try:
        main()
    except Exception as error:
        print('Deployment failed:', type(error).__name__)
        sys.exit(1)
