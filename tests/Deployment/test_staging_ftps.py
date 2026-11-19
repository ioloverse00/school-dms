import ftplib
import importlib.util
import os
from pathlib import Path
import posixpath
import tempfile
import unittest

spec = importlib.util.spec_from_file_location('staging_ftps', 'scripts/deploy/staging_ftps.py')
module = importlib.util.module_from_spec(spec)
spec.loader.exec_module(module)


class FakeFTP:
    def __init__(self, fail=False):
        self.files = {'/site/dms-staging-app/app/a.php': b'old-a', '/site/public_html/staging/assets/b.css': b'old-b'}
        self.dirs = {'/', '/site', '/site/dms-staging-app', '/site/dms-staging-app/app', '/site/public_html', '/site/public_html/staging', '/site/public_html/staging/assets'}
        self.current = '/site'
        self.fail = fail

    def cwd(self, path):
        if path not in self.dirs:
            raise ftplib.error_perm('550')
        self.current = path

    def mkd(self, path):
        self.dirs.add(path)

    def nlst(self):
        return [posixpath.basename(p) for p in self.files if posixpath.dirname(p) == self.current]

    def storbinary(self, command, stream):
        self.files[command[5:]] = stream.read()

    def retrbinary(self, command, callback):
        callback(self.files[command[5:]])

    def rename(self, source, target):
        if self.fail and source.endswith('.tmp') and target.endswith('b.css'):
            raise OSError('Simulated replacement failure')
        self.files[target] = self.files.pop(source)

    def delete(self, path):
        del self.files[path]


class DeploymentTests(unittest.TestCase):
    def test_targets_only_expected_code_and_public_assets(self):
        self.assertEqual(module.destination('app/a.php'), 'dms-staging-app/app/a.php')
        self.assertEqual(module.destination('public/assets/b.css'), 'public_html/staging/assets/b.css')
        self.assertEqual(module.destination('config/openai.php'), 'dms-staging-app/config/openai.php')
        self.assertEqual(module.destination('routes/web.php'), 'dms-staging-app/routes/web.php')
        for path in ('.env', 'storage/a', 'database/a', 'public/index.php', '../app/a', '/app/a', 'app/../.env'):
            with self.assertRaises(ValueError):
                module.destination(path)

    def run_deploy(self, ftp, verify=None):
        original = os.getcwd()
        with tempfile.TemporaryDirectory() as directory:
            try:
                os.chdir(directory)
                Path('a.php').write_bytes(b'new-a')
                Path('b.css').write_bytes(b'new-b')
                module.deploy(ftp, [('a.php', 'dms-staging-app/app/a.php'), ('b.css', 'public_html/staging/assets/b.css')], '/site', 'test', verify)
            finally:
                os.chdir(original)

    def test_success_retains_private_backups_and_cleans_temp_files(self):
        ftp = FakeFTP()
        self.run_deploy(ftp)
        self.assertEqual(ftp.files['/site/dms-staging-app/app/a.php'], b'new-a')
        self.assertEqual(ftp.files['/site/dms-staging-app/storage/deploy-backups/test/dms-staging-app/app/a.php'], b'old-a')
        self.assertEqual(ftp.files['/site/public_html/staging/assets/b.css'], b'new-b')
        self.assertFalse(any(path.endswith('.tmp') for path in ftp.files))

    def test_partial_activation_rolls_back_both_private_and_public_files(self):
        ftp = FakeFTP(fail=True)
        before = dict(ftp.files)
        with self.assertRaises(OSError):
            self.run_deploy(ftp)
        self.assertEqual(ftp.files, before)

    def test_upload_corruption_leaves_live_files_unchanged(self):
        ftp = FakeFTP()
        before = dict(ftp.files)
        ftp.retrbinary = lambda command, callback: callback(b'corrupt')
        with self.assertRaises(ValueError):
            self.run_deploy(ftp)
        self.assertEqual(ftp.files, before)

    def test_failed_health_check_restores_previous_release(self):
        ftp = FakeFTP()
        before = dict(ftp.files)
        def fail_health_check():
            raise ValueError('Unhealthy login')
        with self.assertRaises(ValueError):
            self.run_deploy(ftp, fail_health_check)
        self.assertEqual(ftp.files, before)

    def test_retired_code_is_backed_up_and_restored_on_failure(self):
        ftp = FakeFTP()
        final = '/site/dms-staging-app/app/obsolete.php'
        ftp.files[final] = b'old-code'
        def fail():
            raise ValueError('Unhealthy release')
        with self.assertRaises(ValueError):
            module.deploy(ftp, [(None, 'dms-staging-app/app/obsolete.php')], '/site', 'retire', fail)
        self.assertEqual(ftp.files[final], b'old-code')
        module.deploy(ftp, [(None, 'dms-staging-app/app/obsolete.php')], '/site', 'retire-ok')
        self.assertNotIn(final, ftp.files)

    def test_generated_caches_are_retired_with_rollback(self):
        ftp = FakeFTP()
        directory = '/site/dms-staging-app/bootstrap/cache'
        ftp.dirs.add(directory)
        final = directory + '/config.php'
        ftp.files[final] = b'cached-config'
        before = dict(ftp.files)
        def fail():
            raise ValueError('Unhealthy release')
        with self.assertRaises(ValueError):
            self.run_deploy(ftp, fail)
        self.assertEqual(ftp.files, before)
        self.run_deploy(ftp)
        self.assertNotIn(final, ftp.files)
