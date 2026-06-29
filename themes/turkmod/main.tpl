<!DOCTYPE html>
<html lang="{site_language}" data-theme-mode="{theme_mode}" data-public-theme="{theme_id}">
<head>
{raw:head}
</head>
<body class="site-body {body_class}">

{raw:header}

<main id="main-content" class="site-main site-main--{layout_mode}" tabindex="-1">
{raw:breadcrumbs_html}
<div class="container page-wrap ui-container">
<div class="layout grid grid--{layout_mode}">
{raw:sidebar_left}

<section class="content content-area vstack gap-4">
{raw:content}
</section>

{raw:sidebar_right}
</div></div>
</main>

{raw:footer}

{raw:scripts}
</body>
</html>
