<!doctype html>
<html lang="id">
<head>
    <meta charset="utf-8">

    <title>{{ $title }}</title>

    <meta name="description" content="{{ $description }}">

    <meta property="og:type" content="website">
    <meta property="og:site_name" content="Sena Digital">
    <meta property="og:title" content="{{ $title }}">
    <meta property="og:description" content="{{ $description }}">
    <meta property="og:url" content="{{ $frontendUrl }}">

    <meta property="og:image" content="{{ $coverUrl }}">
    <meta property="og:image:secure_url" content="{{ $coverUrl }}">
    <meta property="og:image:alt" content="{{ $title }}">
    @if (!empty($imageType))
    <meta property="og:image:type" content="{{ $imageType }}">
    @endif
    @if (!empty($imageWidth) && !empty($imageHeight))
    <meta property="og:image:width" content="{{ $imageWidth }}">
    <meta property="og:image:height" content="{{ $imageHeight }}">
    @endif

    <meta name="twitter:card" content="summary_large_image">
    <meta name="twitter:title" content="{{ $title }}">
    <meta name="twitter:description" content="{{ $description }}">
    <meta name="twitter:image" content="{{ $coverUrl }}">

    <link rel="canonical" href="{{ $frontendUrl }}">
</head>

<body>
    <script>
        window.location.replace(@json($frontendUrl));
    </script>

    <noscript>
        <a href="{{ $frontendUrl }}">Buka undangan</a>
    </noscript>
</body>
</html>
