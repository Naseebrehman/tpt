<?php
/**
 * Maintenance mode page — self-contained (no header/footer dependencies).
 */
$siteName = function_exists('getSetting') ? getSetting('site_name', SITE_NAME) : SITE_NAME;
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Be Right Back — <?= htmlspecialchars($siteName, ENT_QUOTES, 'UTF-8') ?></title>
<meta name="robots" content="noindex">
<style>
  :root{--violet:#7c3aed;--violet-soft:#a78bfa}
  *{box-sizing:border-box}
  body{margin:0;min-height:100vh;display:grid;place-items:center;background:#08080a;color:#f4f4f6;font-family:'Inter',system-ui,-apple-system,'Segoe UI',sans-serif;text-align:center;padding:24px;overflow:hidden}
  body::before{content:"";position:fixed;inset:0;background:radial-gradient(45% 45% at 50% 30%,rgba(124,58,237,.22),transparent 65%)}
  .wrap{position:relative;max-width:560px}
  .brand{font-family:'Space Grotesk',system-ui,sans-serif;font-weight:700;font-size:1.2rem;letter-spacing:-.02em}
  .brand i{color:var(--violet);font-style:normal}
  h1{font-family:'Space Grotesk',system-ui,sans-serif;font-size:clamp(2rem,6vw,3.2rem);letter-spacing:-.03em;margin:26px 0 14px}
  p{color:#9a9aa7;line-height:1.75;margin:0 0 30px}
  .gear{width:64px;height:64px;margin:0 auto;border-radius:50%;border:2px solid rgba(167,139,250,.4);border-top-color:var(--violet-soft);animation:spin 1.4s linear infinite}
  @keyframes spin{to{transform:rotate(360deg)}}
  a{color:var(--violet-soft)}
</style>
</head>
<body>
<div class="wrap">
    <div class="brand">The Pie<i>.</i> Technologies</div>
    <div class="gear" aria-hidden="true"></div>
    <h1>We&rsquo;re upgrading the engine.</h1>
    <p>The site is down for scheduled maintenance and will be back shortly. If it&rsquo;s urgent, reach us at
        <a href="mailto:hello@thepietechnologies.com">hello@thepietechnologies.com</a> or on WhatsApp.</p>
</div>
</body>
</html>
