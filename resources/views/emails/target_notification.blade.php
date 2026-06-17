<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Target Notification</title>
    <style>
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background-color: #f4f7fc;
            margin: 0;
            padding: 0;
        }
        .container {
            max-width: 600px;
            margin: 40px auto;
            background: #ffffff;
            border-radius: 16px;
            box-shadow: 0 8px 30px rgba(0,0,0,0.12);
            overflow: hidden;
        }
        .header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            padding: 30px 40px;
            text-align: center;
        }
        .header h1 {
            color: #ffffff;
            margin: 0;
            font-size: 24px;
            font-weight: 700;
            letter-spacing: 0.5px;
        }
        .header p {
            color: rgba(255,255,255,0.9);
            margin: 8px 0 0;
            font-size: 16px;
        }
        .body-content {
            padding: 40px;
        }
        .greeting {
            font-size: 18px;
            color: #1e293b;
            margin-bottom: 20px;
        }
        .greeting strong {
            color: #4f46e5;
        }
        .target-card {
            background: #f8fafc;
            border-radius: 12px;
            padding: 24px;
            margin: 20px 0;
            border-left: 4px solid #667eea;
        }
        .target-card .label {
            font-size: 14px;
            color: #64748b;
            font-weight: 500;
        }
        .target-card .value {
            font-size: 28px;
            font-weight: 700;
            color: #1e293b;
            margin-top: 4px;
        }
        .target-card .period {
            font-size: 14px;
            color: #475569;
            margin-top: 8px;
        }
        .status-badge {
            display: inline-block;
            padding: 6px 16px;
            border-radius: 20px;
            font-size: 14px;
            font-weight: 600;
            margin-top: 12px;
        }
        .status-created {
            background: #dbeafe;
            color: #2563eb;
        }
        .status-updated {
            background: #fef3c7;
            color: #d97706;
        }
        .message-box {
            background: #f1f5f9;
            border-radius: 10px;
            padding: 16px 20px;
            margin: 20px 0;
            color: #334155;
        }
        .footer {
            padding: 24px 40px;
            text-align: center;
            border-top: 1px solid #e2e8f0;
            background: #f8fafc;
        }
        .footer p {
            margin: 0;
            font-size: 14px;
            color: #94a3b8;
        }
        .footer .brand {
            color: #667eea;
            font-weight: 600;
        }
        @media (max-width: 480px) {
            .header { padding: 24px 20px; }
            .body-content { padding: 24px 20px; }
            .target-card .value { font-size: 24px; }
        }
    </style>
</head>
<body>
    <div class="container">
        <!-- Header -->
        <div class="header">
            <h1>🎯 Target Notification</h1>
            <p>Your monthly performance target has been {{ $action === 'created' ? 'assigned' : 'updated' }}</p>
        </div>

        <!-- Body -->
        <div class="body-content">
            <div class="greeting">
                Hello <strong>{{ $user->name }}</strong> 👋,
            </div>

            <p style="color: #475569; line-height: 1.6;">
                @if($action === 'created')
                    A new target has been assigned to you for the month of <strong>{{ $monthName }} {{ $year }}</strong>.
                @else
                    Your target for the month of <strong>{{ $monthName }} {{ $year }}</strong> has been updated.
                @endif
            </p>

            <!-- Target Card -->
            <div class="target-card">
                <div class="label">📊 Your Target Value</div>
                <div class="value">{{ $targetAmount }}</div>
                <div class="period">
                    📅 {{ $monthName }} {{ $year }}
                </div>
                <div>
                    <span class="status-badge {{ $action === 'created' ? 'status-created' : 'status-updated' }}">
                        {{ $action === 'created' ? '✅ New Target' : '🔄 Target Updated' }}
                    </span>
                </div>
            </div>

            <div class="message-box">
                <strong>💡 Tip:</strong>
                @if($action === 'created')
                    Start planning your strategy to achieve this target. Stay focused and consistent!
                @else
                    Review your updated target and adjust your plan accordingly.
                @endif
            </div>

            <p style="color: #64748b; font-size: 14px; margin-top: 24px;">
                You can track your progress anytime in the <strong>Target Management</strong> section of your dashboard.
            </p>
        </div>

        <!-- Footer -->
        <div class="footer">
            <p>
                © {{ date('Y') }} <span class="brand">Target Management System</span>
                <br>
                <small>This is an automated notification. Please do not reply to this email.</small>
            </p>
        </div>
    </div>
</body>
</html>
