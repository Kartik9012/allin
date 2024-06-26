<!DOCTYPE html>
<html>

<head>
    <title>{{ $title }}</title>
</head>

<body>
    <p>Dear Sir / ma'am,</p>

    <p>I hope this email finds you well.</p>

    @if (isset($summary) && !empty($summary))
        <p>{{ $summary }}</p>
    @else
        <p>Please find attached my work hours for the month of {{ $month }}. The attached document includes a
                        detailed breakdown of the hours worked each day, along with the tasks and projects I have been involved in
                        during this period.</p>

        <p>If you have any questions or need further clarification, please do not hesitate to reach out.</p>
    @endif
    <p>Thank you for your attention to this matter.</p>
<p>Best regards,</p>
<p>{{ $name }}</p>
</body>

</html>
