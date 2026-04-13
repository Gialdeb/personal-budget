<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>Support request</title>
</head>
<body style="font-family: Arial, sans-serif; color: #0f172a; line-height: 1.6;">
    <h1 style="font-size: 20px; margin-bottom: 16px;">New support request</h1>

    <p><strong>Category:</strong> {{ $supportRequest->category }}</p>
    <p><strong>Status:</strong> {{ $supportRequest->status }}</p>
    <p><strong>Subject:</strong> {{ $supportRequest->subject }}</p>
    <p><strong>User:</strong> {{ $supportRequest->user?->name ?? 'Unknown user' }}</p>
    <p><strong>Email:</strong> {{ $supportRequest->user?->email ?? 'No email' }}</p>
    <p><strong>Locale:</strong> {{ $supportRequest->locale }}</p>
    <p><strong>Source route:</strong> {{ $supportRequest->source_route ?? 'n/a' }}</p>
    <p><strong>Source URL:</strong> {{ $supportRequest->source_url ?? 'n/a' }}</p>

    <hr style="margin: 24px 0; border: 0; border-top: 1px solid #e2e8f0;">

    <h2 style="font-size: 16px; margin-bottom: 12px;">Message</h2>
    <div style="white-space: pre-wrap;">{{ $supportRequest->message }}</div>
</body>
</html>
