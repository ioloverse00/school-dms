<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <style>
        body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
        .container { max-width: 600px; margin: 0 auto; padding: 20px; }
        .header { background-color: #7e3af2; color: white; padding: 20px; border-radius: 8px 8px 0 0; }
        .content { background-color: #f9f9f9; padding: 20px; border-radius: 0 0 8px 8px; }
        .status-badge { display: inline-block; padding: 6px 12px; border-radius: 20px; font-weight: bold; }
        .info-table { width: 100%; border-collapse: collapse; margin: 15px 0; }
        .info-table td { padding: 8px; border-bottom: 1px solid #ddd; }
        .info-table td:first-child { font-weight: bold; width: 40%; }
        .footer { text-align: center; margin-top: 20px; font-size: 12px; color: #666; }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h2 style="margin:0">School DMS — Request Update</h2>
        </div>
        <div class="content">
            <p>Dear <strong>{{ $documentRequest->user->name }}</strong>,</p>
            <p>Your document request status has been updated. Here are the details:</p>

            <table class="info-table">
                <tr>
                    <td>Request ID:</td>
                    <td>{{ $documentRequest->request_id }}</td>
                </tr>
                <tr>
                    <td>Document:</td>
                    <td>{{ $documentRequest->documentType->name }}</td>
                </tr>
                <tr>
                    <td>Purpose:</td>
                    <td>{{ $documentRequest->purpose }}</td>
                </tr>
                <tr>
                    <td>New Status:</td>
                    <td><strong>{{ ucwords(str_replace('_', ' ', $documentRequest->status)) }}</strong></td>
                </tr>
                @if($documentRequest->remarks)
                <tr>
                    <td>Remarks:</td>
                    <td>{{ $documentRequest->remarks }}</td>
                </tr>
                @endif
            </table>

            @if($documentRequest->status == 'ready_for_release')
            <p style="color: #059669; font-weight: bold;">
                Your document is ready! Please visit the Registrar's Office to claim it.
            </p>
            @elseif($documentRequest->status == 'rejected')
            <p style="color: #dc2626;">
                Your request has been rejected. Please contact the Registrar's Office for more information.
            </p>
            @else
            <p>We will notify you again when there are further updates to your request.</p>
            @endif

            <p>Thank you,<br><strong>School DMS Team</strong></p>
        </div>
        <div class="footer">
            <p>This is an automated message. Please do not reply to this email.</p>
        </div>
    </div>
</body>
</html>