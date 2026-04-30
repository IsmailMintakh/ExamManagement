<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>{{ $subject ?? 'ExamPro Notification' }}</title>
<style>
    body { margin: 0; padding: 0; background: #f1f5f9; font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Helvetica, Arial, sans-serif; color: #1e293b; line-height: 1.5; -webkit-font-smoothing: antialiased; }
    table { border-collapse: collapse; }
    img { border: 0; outline: none; }
    .outer { width: 100%; padding: 20px 10px; background: #f1f5f9; }
    .container { max-width: 560px; margin: 0 auto; background: #fff; border-radius: 12px; overflow: hidden; box-shadow: 0 4px 16px rgba(15,23,42,0.06); }
    .header { background: linear-gradient(135deg, #4f46e5, #7c3aed); padding: 28px 32px; text-align: center; }
    .brand { color: #fff; font-size: 20px; font-weight: 800; letter-spacing: 0.5px; }
    .brand-tag { color: rgba(255,255,255,0.75); font-size: 11px; letter-spacing: 2px; text-transform: uppercase; margin-top: 4px; }
    .body { padding: 32px; }
    .greeting { font-size: 18px; font-weight: 700; color: #0f172a; margin: 0 0 8px; }
    .text { font-size: 14px; color: #475569; margin: 0 0 14px; line-height: 1.6; }
    .text strong { color: #0f172a; }
    .info-card { background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 10px; padding: 16px 20px; margin: 20px 0; }
    .info-row { display: table; width: 100%; padding: 5px 0; }
    .info-label { display: table-cell; font-size: 12px; color: #64748b; font-weight: 600; text-transform: uppercase; letter-spacing: 0.5px; padding-right: 15px; width: 130px; }
    .info-value { display: table-cell; font-size: 13px; color: #0f172a; font-weight: 600; }
    .btn-wrap { text-align: center; margin: 28px 0; }
    .btn { display: inline-block; background: #4f46e5; color: #fff !important; padding: 12px 28px; border-radius: 8px; text-decoration: none; font-weight: 600; font-size: 14px; box-shadow: 0 2px 8px rgba(79,70,229,0.25); }
    .btn:hover { background: #4338ca; }
    .btn-success { background: #10b981; box-shadow: 0 2px 8px rgba(16,185,129,0.25); }
    .btn-warning { background: #f59e0b; box-shadow: 0 2px 8px rgba(245,158,11,0.25); }
    .btn-error { background: #ef4444; box-shadow: 0 2px 8px rgba(239,68,68,0.25); }
    .divider { height: 1px; background: #e2e8f0; margin: 24px 0; }
    .note { background: #fef3c7; border-left: 3px solid #f59e0b; padding: 12px 16px; border-radius: 0 8px 8px 0; font-size: 12px; color: #78350f; margin: 16px 0; }
    .footer { padding: 20px 32px; background: #f8fafc; border-top: 1px solid #e2e8f0; text-align: center; font-size: 11px; color: #94a3b8; line-height: 1.6; }
    .footer a { color: #4f46e5; text-decoration: none; }
    .footer-brand { font-weight: 600; color: #475569; }

    @media (max-width: 600px) {
        .container { border-radius: 0; }
        .body { padding: 20px; }
        .header { padding: 20px; }
        .info-label { width: 100px; padding-right: 10px; }
    }
</style>
</head>
<body>
<div class="outer">
    <div class="container">
        <!-- Header -->
        <div class="header">
            <div class="brand">ExamPro</div>
            <div class="brand-tag">Exam Management System</div>
        </div>

        <!-- Body -->
        <div class="body">
            @yield('content')

            @hasSection('action')
                <div class="btn-wrap">
                    @yield('action')
                </div>
            @endif

            <div class="divider"></div>

            <p class="text" style="font-size: 12px; color: #94a3b8; margin-bottom: 0;">
                If you didn't expect this email, you can safely ignore it. This is an automated message — please do not reply directly.
            </p>
        </div>

        <!-- Footer -->
        <div class="footer">
            <p class="footer-brand">ExamPro</p>
            <p>&copy; {{ date('Y') }} ExamPro. All rights reserved.</p>
            <p style="margin-top: 6px;">Need help? Contact your school administrator.</p>
        </div>
    </div>
</div>
</body>
</html>
