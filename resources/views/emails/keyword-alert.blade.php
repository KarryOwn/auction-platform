<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Keyword Alert</title>
</head>
<body>
    <p>Hello {{ $user->name }},</p>
    <p>A new auction matches your keyword "{{ $keyword }}".</p>
    <p><strong>{{ $auction->title }}</strong></p>
    <p>
        <a href="{{ route('auctions.show', $auction->id) }}">View Auction</a>
    </p>
</body>
</html>