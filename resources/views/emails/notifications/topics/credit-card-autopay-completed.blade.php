@include('emails.notifications.base', [
    'title' => $title,
    'message' => $message,
    'details' => $details,
    'detailsTitle' => $detailsTitle,
    'actionLabel' => $actionLabel,
    'actionUrl' => $actionUrl,
    'footer' => $footer,
])
