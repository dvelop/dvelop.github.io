<?php
declare(strict_types=1);

/* ============================================================
   PROJECT SCANNER
   Durchsucht den Ordner /projects und liest Metadaten aus den
   HTML-Dateien (Titel, Beschreibung, Kategorie, Tags).
   ============================================================ */

/**
 * Liest eine Projekt-HTML-Datei und extrahiert Titel, Beschreibung,
 * Kategorie und Tags. Fällt auf $fallbackTitle zurück, wenn kein
 * <title> gefunden wird.
 */
function parseProjectHtml(string $file, string $fallbackTitle): array
{
    $data = [
        'title'         => $fallbackTitle,
        'desc'          => '',
        'category'      => 'project',
        'categoryLabel' => 'Project',
        'tags'          => [],
    ];

    $html = @file_get_contents($file);
    if ($html === false) {
        return $data;
    }

    /* ---- <title> ---- */
    if (preg_match('/<title[^>]*>(.*?)<\/title>/is', $html, $m)) {
        $title = trim(html_entity_decode($m[1], ENT_QUOTES | ENT_HTML5, 'UTF-8'));
        // Häufige Suffixe entfernen, z. B. "MyApp | dvelop"
        $title = preg_replace('/\s*[|·–—-]\s*(dvelop|Vecchio Lopez Consulting).*$/iu', '', $title);
        if ($title !== '') {
            $data['title'] = $title;
        }
    }

    /* ---- <meta …> Attribute einsammeln (name/content in beliebiger Reihenfolge) ---- */
    $metas = [];
    if (preg_match_all('/<meta\s+([^>]+?)\/?>/i', $html, $tags)) {
        foreach ($tags[1] as $attrs) {
            if (
                preg_match('/name\s*=\s*["\']([^"\']+)["\']/i', $attrs, $n) &&
                preg_match('/content\s*=\s*["\']([^"\']*)["\']/i', $attrs, $c)
            ) {
                $metas[strtolower(trim($n[1]))] = trim(html_entity_decode($c[1], ENT_QUOTES | ENT_HTML5, 'UTF-8'));
            }
        }
    }

    if (!empty($metas['description'])) {
        $data['desc'] = $metas['description'];
    }
    if (!empty($metas['project-category'])) {
        $data['category']      = strtolower(preg_replace('/[^a-z0-9_-]+/i', '-', $metas['project-category']));
        $data['categoryLabel'] = ucfirst(str_replace(['-', '_'], ' ', $data['category']));
    }
    if (!empty($metas['project-tags'])) {
        $tags = array_map('trim', explode(',', $metas['project-tags']));
        $data['tags'] = array_values(array_filter($tags, fn($t) => $t !== ''));
    }

    return $data;
}

/* ---- Ordner /projects durchsuchen ---- */
$projectsDir = __DIR__ . '/projects';
$projects    = [];

if (is_dir($projectsDir)) {
    $entries = @scandir($projectsDir) ?: [];
    foreach ($entries as $entry) {
        if ($entry === '.' || $entry === '..' || $entry[0] === '.') {
            continue;
        }

        $full = $projectsDir . '/' . $entry;

        // Fall A: Unterordner mit index.html (oder index.php)
        if (is_dir($full)) {
            foreach (['index.html', 'index.php'] as $idx) {
                if (is_file($full . '/' . $idx)) {
                    $slug          = $entry;
                    $url           = 'projects/' . rawurlencode($entry) . '/' . $idx;
                    $fallbackTitle = ucwords(str_replace(['-', '_'], ' ', $slug));
                    $meta          = parseProjectHtml($full . '/' . $idx, $fallbackTitle);
                    $projects[]    = array_merge($meta, ['slug' => $slug, 'url' => $url]);
                    break;
                }
            }
        }
        // Fall B: HTML-Datei direkt in /projects
        elseif (is_file($full) && strtolower(pathinfo($full, PATHINFO_EXTENSION)) === 'html') {
            $slug          = pathinfo($full, PATHINFO_FILENAME);
            $url           = 'projects/' . rawurlencode($entry);
            $fallbackTitle = ucwords(str_replace(['-', '_'], ' ', $slug));
            $meta          = parseProjectHtml($full, $fallbackTitle);
            $projects[]    = array_merge($meta, ['slug' => $slug, 'url' => $url]);
        }
    }
}

// Alphabetisch nach Titel sortieren
usort($projects, static fn(array $a, array $b): int => strcasecmp($a['title'], $b['title']));

// Kategorien für die Filter-Chips sammeln
$categories = [];
foreach ($projects as $p) {
    $categories[$p['category']] = $p['categoryLabel'];
}

/* ---- Helper: Farbe & Initialen für die Kachel ---- */
function hueForString(string $str): int
{
    $hash = 0;
    $len  = strlen($str);
    for ($i = 0; $i < $len; $i++) {
        $hash = ($hash * 31 + ord($str[$i])) % 1000;
    }
    return 215 + ($hash % 55); // 215 (blau) .. 270 (lila)
}

function initials(string $name): string
{
    $name  = preg_replace('/[^A-Za-z0-9 ]/', '', $name);
    $parts = array_values(array_filter(explode(' ', $name)));
    if (count($parts) === 0)  return '??';
    if (count($parts) === 1)  return strtoupper(substr($parts[0], 0, 2));
    return strtoupper($parts[0][0] . $parts[1][0]);
}

function e(?string $v): string
{
    return htmlspecialchars((string)$v, ENT_QUOTES | ENT_HTML5, 'UTF-8');
}

$year = date('Y');
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>dvelop | Software &amp; AI Development from Basel</title>
<meta name="description" content="dvelop – Portfolio of software and AI projects: SaaS platforms, AI assistants, and dashboards for Swiss companies.">
<meta name="theme-color" content="#07070f">
<link rel="icon" href="data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 100 100'%3E%3Cdefs%3E%3ClinearGradient id='g' x1='0' y1='0' x2='1' y2='1'%3E%3Cstop offset='0' stop-color='%234f7dfb'/%3E%3Cstop offset='1' stop-color='%23b15bff'/%3E%3C/linearGradient%3E%3C/defs%3E%3Crect width='100' height='100' rx='22' fill='url(%23g)'/%3E%3Ctext x='50' y='68' font-size='58' font-family='Arial,sans-serif' font-weight='700' fill='white' text-anchor='middle'%3Ed%3C/text%3E%3C/svg%3E">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Space+Grotesk:wght@500;600;700&family=Inter:wght@400;500;600;700&family=JetBrains+Mono:wght@400;500&display=swap" rel="stylesheet">
<style>

/* ============================================================
   TOKENS
   ============================================================ */
:root{
  --bg:#07070f;
  --bg-elevated:#0c0c18;
  --surface:rgba(255,255,255,0.04);
  --surface-hover:rgba(255,255,255,0.07);
  --border:rgba(255,255,255,0.09);
  --border-strong:rgba(255,255,255,0.18);
  --text:#f3f3f8;
  --text-muted:#9b9bb0;
  --text-dim:#6b6b80;
  --blue:#4f7dfb;
  --blue-deep:#3458d9;
  --purple:#b15bff;
  --purple-deep:#8a3fe0;
  --gradient:linear-gradient(135deg,var(--blue),var(--purple));
  --gradient-soft:linear-gradient(135deg,rgba(79,125,251,0.16),rgba(177,91,255,0.16));
  --radius-lg:20px;
  --radius:14px;
  --radius-sm:8px;
  --font-display:'Space Grotesk',sans-serif;
  --font-body:'Inter',sans-serif;
  --font-mono:'JetBrains Mono',monospace;
  --ease:cubic-bezier(.16,1,.3,1);
  --nav-h:76px;
}

/* ============================================================
   RESET / BASE
   ============================================================ */
*,*::before,*::after{box-sizing:border-box;margin:0;padding:0;}
html{scroll-behavior:smooth;}
body{
  background:var(--bg);
  color:var(--text);
  font-family:var(--font-body);
  font-size:16px;
  line-height:1.6;
  -webkit-font-smoothing:antialiased;
  overflow-x:hidden;
}
img,svg{display:block;max-width:100%;}
a{color:inherit;text-decoration:none;}
ul{list-style:none;}
button{font:inherit;color:inherit;background:none;border:none;cursor:pointer;}
section,header,footer{position:relative;}
h1,h2,h3{font-family:var(--font-display);font-weight:600;letter-spacing:-0.01em;}
.container{width:100%;max-width:1180px;margin:0 auto;padding:0 28px;}
.grad-text{background:var(--gradient);-webkit-background-clip:text;background-clip:text;color:transparent;}
:focus-visible{outline:2px solid var(--blue);outline-offset:3px;border-radius:4px;}
.visually-hidden{position:absolute;width:1px;height:1px;overflow:hidden;clip:rect(0 0 0 0);white-space:nowrap;}
.skip-link{position:absolute;left:-9999px;top:0;background:var(--purple);color:#fff;padding:10px 18px;border-radius:0 0 8px 0;z-index:200;}
.skip-link:focus{left:0;}

.eyebrow{
  font-family:var(--font-mono);font-size:12.5px;letter-spacing:0.14em;text-transform:uppercase;
  color:var(--text-muted);display:inline-flex;align-items:center;gap:10px;
}
.eyebrow::before{content:'';width:7px;height:7px;border-radius:50%;background:var(--gradient);flex-shrink:0;}

.btn{
  display:inline-flex;align-items:center;gap:8px;
  font-family:var(--font-body);font-weight:600;font-size:15px;
  padding:14px 26px;border-radius:999px;
  transition:transform .35s var(--ease),box-shadow .35s var(--ease),border-color .35s var(--ease),background .35s var(--ease);
  white-space:nowrap;
}
.btn-primary{background:var(--gradient);color:#fff;box-shadow:0 8px 24px -8px rgba(140,90,255,0.55);}
.btn-primary:hover{transform:translateY(-2px);box-shadow:0 14px 32px -8px rgba(140,90,255,0.7);}
.btn-secondary{background:var(--surface);border:1px solid var(--border-strong);color:var(--text);}
.btn-secondary:hover{background:var(--surface-hover);border-color:rgba(255,255,255,0.3);transform:translateY(-2px);}

/* ============================================================
   SCROLL REVEAL
   ============================================================ */
.reveal{opacity:0;transform:translateY(24px);transition:opacity .7s var(--ease),transform .7s var(--ease);}
.reveal.is-visible{opacity:1;transform:translateY(0);}

/* ============================================================
   NAVIGATION
   ============================================================ */
.nav{
  position:fixed;top:0;left:0;right:0;z-index:100;height:var(--nav-h);
  display:flex;align-items:center;
  background:rgba(7,7,15,0.5);
  backdrop-filter:blur(14px);-webkit-backdrop-filter:blur(14px);
  border-bottom:1px solid transparent;
  transition:border-color .4s var(--ease),background .4s var(--ease);
}
.nav.is-scrolled{background:rgba(7,7,15,0.78);border-bottom-color:var(--border);}
.nav-inner{width:100%;max-width:1180px;margin:0 auto;padding:0 28px;display:flex;align-items:center;justify-content:space-between;}
.logo{display:flex;align-items:center;gap:9px;font-family:var(--font-display);font-weight:700;font-size:19px;}
.logo-mark{font-family:var(--font-mono);font-weight:500;font-size:17px;background:var(--gradient);-webkit-background-clip:text;background-clip:text;color:transparent;}
.nav-links{display:flex;align-items:center;gap:36px;}
.nav-links a{font-size:14.5px;font-weight:500;color:var(--text-muted);position:relative;padding:6px 0;transition:color .3s var(--ease);}
.nav-links a::after{content:'';position:absolute;left:0;bottom:0;height:2px;width:0;background:var(--gradient);border-radius:2px;transition:width .35s var(--ease);}
.nav-links a:hover,.nav-links a.active{color:var(--text);}
.nav-links a:hover::after,.nav-links a.active::after{width:100%;}
.nav-cta{display:flex;align-items:center;gap:18px;}
.nav-cta .btn{padding:10px 20px;font-size:14px;}

.nav-toggle{display:none;flex-direction:column;justify-content:center;gap:5px;width:38px;height:38px;border-radius:8px;}
.nav-toggle span{width:20px;height:2px;background:var(--text);border-radius:2px;transition:transform .3s var(--ease),opacity .3s var(--ease);}
.nav-toggle[aria-expanded="true"] span:nth-child(1){transform:translateY(7px) rotate(45deg);}
.nav-toggle[aria-expanded="true"] span:nth-child(2){opacity:0;}
.nav-toggle[aria-expanded="true"] span:nth-child(3){transform:translateY(-7px) rotate(-45deg);}

.mobile-menu{
  position:fixed;inset:0;top:var(--nav-h);z-index:99;
  background:rgba(7,7,15,0.98);backdrop-filter:blur(10px);
  display:flex;flex-direction:column;align-items:center;justify-content:center;gap:30px;
  transform:translateY(-12px);opacity:0;visibility:hidden;
  transition:opacity .35s var(--ease),transform .35s var(--ease),visibility .35s;
}
.mobile-menu.is-open{opacity:1;transform:translateY(0);visibility:visible;}
.mobile-menu a{font-family:var(--font-display);font-size:26px;font-weight:600;color:var(--text);}
.mobile-menu .btn{margin-top:8px;}

/* ============================================================
   HERO
   ============================================================ */
.hero{
  min-height:100vh;display:flex;flex-direction:column;justify-content:center;
  padding-top:var(--nav-h);scroll-margin-top:var(--nav-h);overflow:hidden;
}
.hero-bg{position:absolute;inset:0;z-index:-1;overflow:hidden;}
.hero-bg .grid-pattern{
  position:absolute;inset:0;
  background-image:
    linear-gradient(rgba(255,255,255,0.035) 1px,transparent 1px),
    linear-gradient(90deg,rgba(255,255,255,0.035) 1px,transparent 1px);
  background-size:42px 42px;
  mask-image:radial-gradient(ellipse 70% 60% at 50% 30%,black 0%,transparent 75%);
}
.blob{position:absolute;border-radius:50%;filter:blur(90px);opacity:0.55;animation:float 18s ease-in-out infinite;}
.blob-1{width:480px;height:480px;background:var(--blue);top:-180px;left:-120px;}
.blob-2{width:420px;height:420px;background:var(--purple);top:60px;right:-140px;animation-delay:-6s;}
.blob-3{width:340px;height:340px;background:var(--purple-deep);bottom:-200px;left:30%;animation-delay:-11s;opacity:0.4;}
@keyframes float{
  0%,100%{transform:translate(0,0) scale(1);}
  33%{transform:translate(30px,-25px) scale(1.06);}
  66%{transform:translate(-25px,20px) scale(0.96);}
}

.hero-content{position:relative;z-index:1;max-width:760px;padding:60px 28px 0;margin:0 auto;text-align:center;}
.hero-content .eyebrow{justify-content:center;margin-bottom:26px;}
.hero-content h1{font-size:clamp(2.4rem,5.5vw,4.1rem);line-height:1.08;margin-bottom:24px;}
.hero-content p.lead{font-size:clamp(1.02rem,1.6vw,1.18rem);color:var(--text-muted);max-width:560px;margin:0 auto 38px;}
.hero-cta{display:flex;align-items:center;justify-content:center;gap:16px;flex-wrap:wrap;}

.marquee-wrap{
  position:relative;z-index:1;margin-top:88px;padding:22px 0;
  border-top:1px solid var(--border);border-bottom:1px solid var(--border);
  background:linear-gradient(90deg,rgba(255,255,255,0.015),rgba(255,255,255,0.035),rgba(255,255,255,0.015));
  overflow:hidden;
  -webkit-mask-image:linear-gradient(90deg,transparent,black 8%,black 92%,transparent);
  mask-image:linear-gradient(90deg,transparent,black 8%,black 92%,transparent);
}
.marquee-track{display:flex;align-items:center;gap:14px;width:max-content;animation:scroll-left 60s linear infinite;animation-play-state:running !important;}
.marquee-wrap:hover .marquee-track{animation-play-state:paused !important;}
.tech-pill{
  font-family:var(--font-mono);font-size:13px;color:var(--text-muted);
  padding:8px 18px;border:1px solid var(--border);border-radius:999px;
  background:var(--surface);white-space:nowrap;
}
@keyframes scroll-left{from{transform:translateX(0);}to{transform:translateX(-50%);}}

@media (prefers-reduced-motion:reduce){
  .marquee-track{animation:scroll-left 60s linear infinite !important;}
}

/* ============================================================
   PROJECTS
   ============================================================ */
.projects{padding:130px 0 110px;scroll-margin-top:var(--nav-h);}
.section-head{text-align:center;max-width:600px;margin:0 auto 56px;}
.section-head .eyebrow{justify-content:center;margin-bottom:18px;}
.section-head h2{font-size:clamp(1.9rem,3.4vw,2.6rem);margin-bottom:16px;}
.section-head p{color:var(--text-muted);font-size:1.02rem;}

.filters{display:flex;justify-content:center;gap:10px;flex-wrap:wrap;margin-bottom:48px;}
.chip{
  font-size:13.5px;font-weight:500;padding:9px 18px;border-radius:999px;
  border:1px solid var(--border);background:var(--surface);color:var(--text-muted);
  transition:all .3s var(--ease);
}
.chip:hover{border-color:var(--border-strong);color:var(--text);}
.chip.is-active{background:var(--gradient);color:#fff;border-color:transparent;}

.grid{display:grid;grid-template-columns:repeat(3,1fr);gap:22px;}
.card{
  display:flex;flex-direction:column;
  background:var(--surface);border:1px solid var(--border);
  border-radius:var(--radius-lg);padding:26px;
  transition:transform .4s var(--ease),border-color .4s var(--ease),box-shadow .4s var(--ease),background .4s var(--ease);
}
.card:hover{
  transform:translateY(-6px);border-color:rgba(255,255,255,0.22);
  background:var(--surface-hover);box-shadow:0 20px 48px -16px rgba(110,80,255,0.35);
}
.card-tile{
  width:52px;height:52px;border-radius:14px;
  display:flex;align-items:center;justify-content:center;
  font-family:var(--font-display);font-weight:700;font-size:18px;color:#fff;
  margin-bottom:20px;flex-shrink:0;
}
.card-cat{font-family:var(--font-mono);font-size:11px;letter-spacing:0.08em;text-transform:uppercase;color:var(--text-dim);margin-bottom:8px;}
.card h3{font-size:1.18rem;margin-bottom:10px;}
.card p.desc{color:var(--text-muted);font-size:14.5px;line-height:1.55;flex:1;margin-bottom:18px;}
.card-tags{display:flex;flex-wrap:wrap;gap:7px;margin-bottom:22px;}
.tag{font-family:var(--font-mono);font-size:11.5px;color:var(--text-muted);background:rgba(255,255,255,0.05);border:1px solid var(--border);padding:4px 10px;border-radius:6px;}
.card-link{display:inline-flex;align-items:center;gap:7px;font-size:14px;font-weight:600;color:var(--text);padding-top:14px;border-top:1px solid var(--border);}
.card-link svg{width:14px;height:14px;transition:transform .3s var(--ease);}
.card:hover .card-link svg{transform:translate(3px,-3px);}

.no-results{display:none;text-align:center;color:var(--text-dim);padding:60px 0;font-size:15px;}

/* ============================================================
   FOOTER
   ============================================================ */
.footer{border-top:1px solid var(--border);background:var(--bg-elevated);scroll-margin-top:var(--nav-h);}
.footer-top{height:2px;background:var(--gradient);}
.footer-inner{padding:74px 0 36px;display:grid;grid-template-columns:1.4fr 1fr 1fr;gap:40px;}
.footer-brand p{color:var(--text-muted);font-size:14.5px;margin-top:14px;max-width:300px;}
.footer-col h4{font-family:var(--font-mono);font-size:12px;letter-spacing:0.1em;text-transform:uppercase;color:var(--text-dim);margin-bottom:16px;}
.footer-col ul{display:flex;flex-direction:column;gap:11px;}
.footer-col a{color:var(--text-muted);font-size:14.5px;transition:color .3s var(--ease);}
.footer-col a:hover{color:var(--text);}
.footer-bottom{border-top:1px solid var(--border);padding:24px 0;display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:14px;}
.footer-bottom p{font-size:13px;color:var(--text-dim);}
.to-top{display:flex;align-items:center;gap:6px;font-size:13px;color:var(--text-muted);border:1px solid var(--border);border-radius:999px;padding:8px 16px;transition:all .3s var(--ease);}
.to-top:hover{color:var(--text);border-color:var(--border-strong);}

/* ============================================================
   RESPONSIVE
   ============================================================ */
@media (max-width:1024px){
  .grid{grid-template-columns:repeat(2,1fr);}
  .footer-inner{grid-template-columns:1.2fr 1fr 1fr;gap:28px;}
}
@media (max-width:760px){
  .nav-links,.nav-cta .btn-secondary{display:none;}
  .nav-toggle{display:flex;}
  .hero-content{padding-top:30px;}
  .hero-cta{flex-direction:column;width:100%;}
  .hero-cta .btn{width:100%;justify-content:center;}
  .marquee-wrap{margin-top:60px;}
  .projects{padding:90px 0 80px;}
  .grid{grid-template-columns:1fr;}
  .footer-inner{grid-template-columns:1fr;gap:32px;padding:56px 0 30px;}
  .filters{justify-content:flex-start;overflow-x:auto;padding-bottom:4px;flex-wrap:nowrap;}
}
@media (max-width:480px){
  .container{padding:0 20px;}
  .nav-inner{padding:0 20px;}
  .hero-content h1{font-size:2.1rem;}
}

@media (prefers-reduced-motion:reduce){
  html{scroll-behavior:auto;}
  .blob{animation:none;}
  .reveal{opacity:1;transform:none;transition:none;}
  .marquee-track{animation:scroll-left 60s linear infinite !important;}
  *{transition-duration:.01ms !important;}
}
</style>
</head>
<body>

<a href="#main" class="skip-link">Skip to content</a>

<!-- ============================================================
     NAVIGATION
     ============================================================ -->
<nav class="nav" id="nav">
  <div class="nav-inner">
    <a href="#start" class="logo" aria-label="dvelop Homepage">
      <span class="logo-mark">&lt;/&gt;</span>dvelop
    </a>
    <ul class="nav-links">
      <li><a href="#start" data-nav>Home</a></li>
      <li><a href="#projekte" data-nav>Projects</a></li>
      <li><a href="#kontakt" data-nav>Contact</a></li>
    </ul>
    <div class="nav-cta">
      <a href="#kontakt" class="btn btn-secondary">Contact</a>
      <button class="nav-toggle" id="navToggle" aria-label="Open menu" aria-expanded="false" aria-controls="mobileMenu">
        <span></span><span></span><span></span>
      </button>
    </div>
  </div>
</nav>

<div class="mobile-menu" id="mobileMenu">
  <a href="#start" data-nav>Home</a>
  <a href="#projekte" data-nav>Projects</a>
  <a href="#kontakt" data-nav>Contact</a>
  <a href="#kontakt" class="btn btn-primary" data-nav>Get in touch</a>
</div>

<main id="main">

  <!-- ============================================================
       HERO
       ============================================================ -->
  <header class="hero" id="start">
    <div class="hero-bg" aria-hidden="true">
      <div class="grid-pattern"></div>
      <div class="blob blob-1"></div>
      <div class="blob blob-2"></div>
      <div class="blob blob-3"></div>
    </div>

    <div class="hero-content">
      <p class="eyebrow reveal">Software &amp; AI Development · Basel, Switzerland</p>
      <h1 class="reveal">
        From idea to<br>
        <span class="grad-text">production-ready prototype.</span>
      </h1>
      <p class="lead reveal">
        I develop AI-powered Tools, Apps, SaaS platforms, dashboards, and local assistants for companies – from the first sketch to a production-ready solution.
      </p>
      <div class="hero-cta reveal">
        <a href="#projekte" class="btn btn-primary">View projects</a>
        <a href="#kontakt" class="btn btn-secondary">Get in touch</a>
      </div>
    </div>

    <div class="marquee-wrap reveal">
      <p class="visually-hidden">Technologies used: FastAPI, Ollama, LangChain, LangGraph, ChromaDB, Alpine.js, Tailwind CSS, PostgreSQL, SQLite, Docker, Streamlit, n8n, Vanilla JS, Chart.js, Odoo-Framework, RBAC, IndexedDB, JSON-Modules, Canvas API, Glassmorphism, Multi-tenant Architecture, Stripe API, PayPal API, REST API</p>
      <div class="marquee-track" id="marqueeTrack" aria-hidden="true"></div>
    </div>
  </header>

  <!-- ============================================================
       PROJECTS
       ============================================================ -->
  <section class="projects" id="projekte">
    <div class="container">
      <div class="section-head reveal">
        <p class="eyebrow">Portfolio</p>
        <h2>Selected Projects</h2>
        <p>
          A selection of demos, AI tools, and platforms from recent months. Just open a card and try it out.
        </p>
      </div>

      <?php if (count($categories) > 1): ?>
      <div class="filters reveal" id="filters">
        <button class="chip is-active" data-filter="all">All</button>
        <?php foreach ($categories as $catId => $catLabel): ?>
          <button class="chip" data-filter="<?= e($catId) ?>"><?= e($catLabel) ?></button>
        <?php endforeach; ?>
      </div>
      <?php endif; ?>

      <div class="grid" id="projectGrid">
        <?php if (empty($projects)): ?>
          <p style="color:var(--text-muted);text-align:center;padding:40px 0;grid-column:1/-1;">
            No projects found in the <code>/projects</code> folder yet.
          </p>
        <?php else: ?>
          <?php foreach ($projects as $p):
            $hueA = hueForString($p['slug']);
            $hueB = ($hueA + 35) % 360;
            $tileStyle = 'background:linear-gradient(135deg, hsl(' . $hueA . ',85%,60%), hsl(' . $hueB . ',80%,58%));';
          ?>
            <a class="card reveal"
               href="<?= e($p['url']) ?>"
               target="_blank"
               rel="noopener"
               data-category="<?= e($p['category']) ?>">

              <div class="card-tile" style="<?= e($tileStyle) ?>"><?= e(initials($p['title'])) ?></div>
              <p class="card-cat"><?= e($p['categoryLabel']) ?></p>
              <h3><?= e($p['title']) ?></h3>

              <?php if ($p['desc'] !== ''): ?>
                <p class="desc"><?= e($p['desc']) ?></p>
              <?php else: ?>
                <p class="desc" style="opacity:0.55;">—</p>
              <?php endif; ?>

              <?php if (!empty($p['tags'])): ?>
              <div class="card-tags">
                <?php foreach ($p['tags'] as $tag): ?>
                  <span class="tag"><?= e($tag) ?></span>
                <?php endforeach; ?>
              </div>
              <?php else: ?>
                <div class="card-tags"></div>
              <?php endif; ?>

              <span class="card-link">
                Open project
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                  <line x1="7" y1="17" x2="17" y2="7"></line>
                  <polyline points="7 7 17 7 17 17"></polyline>
                </svg>
              </span>
            </a>
          <?php endforeach; ?>
        <?php endif; ?>
      </div>
      <p class="no-results" id="noResults">No projects in this category.</p>
    </div>
  </section>

</main>

<!-- ============================================================
     FOOTER
     ============================================================ -->
<footer class="footer" id="kontakt">
  <div class="footer-top"></div>
  <div class="container">
    <div class="footer-inner">
      <div class="footer-brand">
        <a href="#start" class="logo" aria-label="dvelop Homepage">
          <span class="logo-mark">&lt;/&gt;</span>dvelop
        </a>
        <p>Software &amp; AI development for companies – by Vecchio Lopez Consulting.</p>
      </div>
      <div class="footer-col">
        <h4>Navigation</h4>
        <ul>
          <li><a href="#start">Home</a></li>
          <li><a href="#projekte">Projects</a></li>
          <li><a href="#kontakt">Contact</a></li>
        </ul>
      </div>
      <div class="footer-col">
        <h4>Contact</h4>
        <ul>
          <li><a href="#">D. Vecchio Lopez</a></li>
          <li><a href="https://www.linkedin.com/in/vecchiolopez" target="_blank" rel="noopener">linkedin.com/in/vecchiolopez</a></li>
          <li><a href="https://github.com/dvelop/" target="_blank" rel="noopener">github.com/dvelop</a></li>
        </ul>
      </div>
    </div>
    <div class="footer-bottom">
      <p>© <span id="year"><?= e($year) ?></span> dvelop — Vecchio Lopez Consulting</p>
      <button class="to-top" id="toTop">↑ Back to top</button>
    </div>
  </div>
</footer>

<script>
(function(){
  'use strict';

  /* ============================================================
     MARQUEE
     ============================================================ */
  var techStack = [
    'FastAPI', 'Ollama', 'LangChain', 'LangGraph', 'ChromaDB',
    'Alpine.js', 'Tailwind CSS', 'PostgreSQL', 'SQLite', 'Docker',
    'Streamlit', 'n8n', 'Vanilla JS', 'Chart.js', 'Odoo-Framework',
    'RBAC', 'IndexedDB', 'JSON-Modules', 'Canvas API', 'Glassmorphism',
    'Multi-tenant Architecture', 'Stripe API', 'PayPal API', 'REST API'
  ];
  var marqueeEl = document.getElementById('marqueeTrack');
  var html = '';
  for (var rep = 0; rep < 2; rep++){
    for (var m = 0; m < techStack.length; m++){
      html += '<span class="tech-pill">' + techStack[m] + '</span>';
    }
  }
  marqueeEl.innerHTML = html;

  /* ============================================================
     FILTERS (nur wenn Kategorien vorhanden)
     ============================================================ */
  var filtersEl = document.getElementById('filters');
  var gridEl    = document.getElementById('projectGrid');
  var noResults = document.getElementById('noResults');

  if (filtersEl && gridEl){
    filtersEl.addEventListener('click', function(e){
      var btn = e.target.closest('.chip');
      if (!btn) return;

      var current = filtersEl.querySelector('.chip.is-active');
      if (current) current.classList.remove('is-active');
      btn.classList.add('is-active');

      var filter = btn.getAttribute('data-filter');
      var cards = gridEl.querySelectorAll('.card');
      var visible = 0;
      cards.forEach(function(card){
        var match = (filter === 'all' || card.getAttribute('data-category') === filter);
        card.style.display = match ? '' : 'none';
        if (match) visible++;
      });
      noResults.style.display = visible === 0 ? 'block' : 'none';
    });
  }

  /* ============================================================
     MOBILE NAV
     ============================================================ */
  var navToggle  = document.getElementById('navToggle');
  var mobileMenu = document.getElementById('mobileMenu');

  function closeMobileMenu(){
    mobileMenu.classList.remove('is-open');
    navToggle.setAttribute('aria-expanded', 'false');
    navToggle.setAttribute('aria-label', 'Open menu');
  }

  navToggle.addEventListener('click', function(){
    var isOpen = mobileMenu.classList.toggle('is-open');
    navToggle.setAttribute('aria-expanded', isOpen ? 'true' : 'false');
    navToggle.setAttribute('aria-label', isOpen ? 'Close menu' : 'Open menu');
  });

  mobileMenu.querySelectorAll('a').forEach(function(link){
    link.addEventListener('click', closeMobileMenu);
  });

  /* ============================================================
     SCROLLED NAV STATE + SCROLLSPY + BACK TO TOP
     ============================================================ */
  var navEl     = document.getElementById('nav');
  var toTopBtn  = document.getElementById('toTop');
  var navLinks  = document.querySelectorAll('a[data-nav]');
  var sections  = ['start', 'projekte', 'kontakt'].map(function(id){
    return document.getElementById(id);
  });

  function setActiveLink(id){
    navLinks.forEach(function(link){
      var target = link.getAttribute('href').replace('#', '');
      link.classList.toggle('active', target === id);
    });
  }

  window.addEventListener('scroll', function(){
    var scrolled = window.scrollY > 20;
    navEl.classList.toggle('is-scrolled', scrolled);
  }, { passive: true });

  if ('IntersectionObserver' in window){
    var spy = new IntersectionObserver(function(entries){
      entries.forEach(function(entry){
        if (entry.isIntersecting) setActiveLink(entry.target.id);
      });
    }, { rootMargin: '-45% 0px -50% 0px' });
    sections.forEach(function(s){ if (s) spy.observe(s); });

    var reveal = new IntersectionObserver(function(entries, obs){
      entries.forEach(function(entry){
        if (entry.isIntersecting){
          entry.target.classList.add('is-visible');
          obs.unobserve(entry.target);
        }
      });
    }, { threshold: 0.12 });

    document.querySelectorAll('.reveal').forEach(function(el){
      reveal.observe(el);
    });
  } else {
    document.querySelectorAll('.reveal').forEach(function(el){ el.classList.add('is-visible'); });
  }

  toTopBtn.addEventListener('click', function(){
    window.scrollTo({ top: 0, behavior: 'smooth' });
  });

})();
</script>
</body>
</html>
