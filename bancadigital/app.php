<?php require_once dirname(__DIR__) . '/includes/paths.php'; ?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Banca Per-Favore — Favor de Jack</title>
<link rel="icon" type="image/webp" href="iconhand.webp">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Cinzel:wght@500;600;700&family=Cinzel+Decorative:wght@700&family=EB+Garamond:ital,wght@0,400;0,500;0,600;1,400&display=swap" rel="stylesheet">
<style>
:root{
  --parchment:#EFE3C6;
  --parchment-deep:#E2D2A8;
  --parchment-shadow:#C9B482;
  --ink:#2E2116;
  --ink-soft:#5B4632;
  --burgundy:#6E1F2C;
  --burgundy-dark:#4A121C;
  --forest:#22402F;
  --forest-dark:#152A1E;
  --gold:#A97C1F;
  --gold-bright:#CDA23A;
  --ppt:#5E7A96;
  --ppt-dark:#33475A;
  --po:#C9A227;
  --po-dark:#7A5C12;
  --pl:#9AA0A6;
  --pl-dark:#565b60;
  --pc:#A15A2A;
  --pc-dark:#6B3A18;
  --shadow-soft: 0 6px 18px rgba(46,33,22,.25);
  --shadow-deep: 0 12px 32px rgba(46,33,22,.4);
}
*{box-sizing:border-box;}
html,body{margin:0;padding:0;}
body{
  background:
    radial-gradient(circle at 12% 8%, rgba(205,162,58,.18), transparent 28%),
    radial-gradient(circle at 88% 22%, rgba(110,31,44,.15), transparent 30%),
    linear-gradient(rgba(27,15,9,.18),rgba(27,15,9,.18)),
    repeating-linear-gradient(90deg,#4a2d1c 0 3px,#593620 4px 72px,#321e14 73px 76px);
  background-attachment:fixed;
  color:var(--ink);
  font-family:'EB Garamond', Georgia, serif;
  font-size:18px;
  line-height:1.5;
  min-height:100vh;
  position:relative;
}
body::before{
  content:"";
  position:fixed; inset:0; pointer-events:none; z-index:0;
  opacity:.62;
  background-image:url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='200' height='200'%3E%3Cfilter id='n'%3E%3CfeTurbulence type='fractalNoise' baseFrequency='0.9' numOctaves='2' stitchTiles='stitch'/%3E%3CfeColorMatrix type='matrix' values='0 0 0 0 0.18  0 0 0 0 0.13  0 0 0 0 0.08  0 0 0 0.05 0'/%3E%3C/filter%3E%3Crect width='100%25' height='100%25' filter='url(%23n)'/%3E%3C/svg%3E");
}
h1,h2,h3,.display{ font-family:'Cinzel', Georgia, serif; letter-spacing:.02em; }
.wordmark{ font-family:'Cinzel Decorative', 'Cinzel', serif; }
#app, #toast-container, #modal-overlay{ position:relative; z-index:1; }

/* ---------- layout shell ---------- */
.sticky-shell{position:sticky;top:0;z-index:100;}
.topbar{
  position:relative; overflow:hidden;
  background:
    radial-gradient(circle at 16% -30%, rgba(205,162,58,.18), transparent 38%),
    radial-gradient(circle at 84% 130%, rgba(205,162,58,.10), transparent 42%),
    linear-gradient(135deg, #25160f 0%, var(--burgundy-dark) 48%, var(--forest-dark) 100%);
  border-bottom:3px double rgba(205,162,58,.72);
  box-shadow:0 8px 24px rgba(46,33,22,.25);
  color:var(--parchment);
}
.topbar::after{
  content:""; position:absolute; inset:0; pointer-events:none; opacity:.12;
  background-image:repeating-linear-gradient(90deg, transparent 0 27px, rgba(255,255,255,.04) 28px 29px);
}
.topbar-inner{
  position:relative; z-index:1; max-width:1080px; margin:0 auto; padding:18px 20px;
  display:grid; grid-template-columns:auto 1fr auto; gap:16px; align-items:center;
}
.brand-copy{ min-width:0; }
.seal{
  width:62px; height:62px; border-radius:50%;
  overflow:hidden;
  display:flex; align-items:center; justify-content:center;
  background:#f3f1ef;
  box-shadow:0 8px 20px rgba(0,0,0,.3), 0 0 0 2px rgba(205,162,58,.65);
  transform:scale(.4) rotate(-25deg); opacity:0;
  animation:stamp .7s cubic-bezier(.2,1.4,.4,1) .15s forwards;
}
.seal img{
  width:100%;
  height:100%;
  display:block;
  object-fit:cover;
  object-position:center;
}
@keyframes stamp{ to{ transform:scale(1) rotate(0deg); opacity:1; } }
.app-title{ font-size:clamp(1.55rem,3vw,2.15rem); color:#F6E8C8; margin:0; text-transform:uppercase; text-shadow:0 2px 0 rgba(0,0,0,.22); }
.app-subtitle{ font-size:.96rem; color:#D8C5A2; margin:2px 0 0; font-style:italic; }
.storage-pill{
  display:flex; align-items:center; gap:8px; white-space:nowrap;
  color:#EAD9B6; border:1px solid rgba(205,162,58,.38); background:rgba(0,0,0,.16);
  border-radius:999px; padding:7px 11px; font-size:.8rem;
}
.storage-dot{ width:8px; height:8px; border-radius:50%; background:#86A77B; box-shadow:0 0 0 3px rgba(134,167,123,.12); }
@media (max-width:680px){
  .topbar-inner{ grid-template-columns:auto 1fr; padding:15px 16px; gap:12px; }
  .storage-pill{ grid-column:1/-1; justify-self:start; margin-left:74px; margin-top:-7px; }
  .seal{ width:58px; height:58px; }
}

.container{
  max-width:1080px;
  margin:18px auto 28px;
  padding:24px 20px 70px;
  border:1px solid rgba(205,162,58,.28);
  border-radius:24px;
  background:linear-gradient(145deg,rgba(239,227,198,.96),rgba(211,188,137,.94));
  box-shadow:0 24px 70px rgba(12,7,4,.48), inset 0 0 70px rgba(91,54,24,.12);
}
.screen{ display:none; animation: fadeIn .35s ease; }
.screen.active{ display:block; }
@keyframes fadeIn{ from{opacity:0; transform:translateY(6px);} to{opacity:1; transform:translateY(0);} }

.back-row{ display:flex; gap:10px; flex-wrap:wrap; margin-bottom:18px; }
.back-btn{
  background:none; border:1px solid var(--parchment-shadow); color:var(--ink-soft);
  font-family:'EB Garamond', serif; font-size:.95rem; padding:8px 14px; border-radius:8px;
  cursor:pointer; transition:.15s;
}
.back-btn:hover{ background:var(--parchment-deep); color:var(--ink); }

.section-title{ text-align:center; color:var(--burgundy-dark); font-size:1.5rem; margin:6px 0 22px; }
.section-title small{ display:block; font-family:'EB Garamond', serif; font-style:italic; font-size:.9rem; color:var(--ink-soft); margin-top:4px; font-weight:400; text-transform:none; letter-spacing:0;}

.empty-note{ text-align:center; color:var(--ink-soft); font-style:italic; margin-bottom:20px; }
.local-notice{
  max-width:760px; margin:0 auto 20px; padding:10px 14px; border-radius:10px;
  border:1px solid rgba(169,124,31,.35); background:rgba(255,248,234,.62); color:var(--ink-soft);
  text-align:center; font-size:.9rem; box-shadow:0 2px 8px rgba(46,33,22,.08);
}
.local-notice strong{ color:var(--ink); }
.wealth-line{ margin-top:7px; font-size:.82rem; color:var(--gold); font-weight:600; }
.mini-coins{ display:grid; grid-template-columns:repeat(4,1fr); gap:5px; margin:10px 0 12px; }
.mini-coin{
  border:1px solid rgba(91,70,50,.14); border-radius:7px; padding:4px 2px;
  background:rgba(255,255,255,.28); font-size:.72rem; color:var(--ink-soft);
}
.mini-coin strong{ display:block; color:var(--ink); font-size:.88rem; line-height:1.1; }
.mini-coin.mini-ppt{background:linear-gradient(145deg,#dce6f0,#9dafc0);border-color:#71869a}
.mini-coin.mini-po{background:linear-gradient(145deg,#fff0a4,#d4ab36);border-color:#9c7617}
.mini-coin.mini-pl{background:linear-gradient(145deg,#f1f1ef,#b7b9bc);border-color:#85898d}
.mini-coin.mini-pc{background:linear-gradient(145deg,#e9b38d,#bc7040);border-color:#87471f}
.operation-preview{
  border:1px solid rgba(169,124,31,.35); background:rgba(255,248,234,.58); border-radius:9px;
  padding:10px 12px; color:var(--ink-soft); font-size:.9rem; margin:2px 0 14px; text-align:center;
}
.operation-preview strong{ color:var(--burgundy-dark); }
.history-delta .positive{ color:var(--forest); background:rgba(34,64,47,.08); }
.history-delta .negative{ color:var(--burgundy); background:rgba(110,31,44,.08); }
.balance-footnote{ margin:14px 0 0; padding-top:12px; border-top:1px dashed var(--parchment-shadow); color:var(--ink-soft); font-size:.82rem; text-align:center; }

/* ---------- card grid ---------- */
.grid{ display:grid; grid-template-columns:repeat(auto-fill, minmax(190px,1fr)); gap:20px; }
.card{
  position:relative;
  background:
    linear-gradient(135deg,rgba(255,255,255,.36),transparent 38%),
    repeating-linear-gradient(0deg,rgba(74,43,20,.045) 0 1px,transparent 1px 7px),
    linear-gradient(160deg,#f5e9cb,#d9bd82);
  border-radius:13px;
  padding:20px 16px;
  box-shadow:var(--shadow-soft);
  cursor:pointer;
  border:1px solid #9b742d;
  transition:transform .18s ease, box-shadow .18s ease;
  text-align:center;
  isolation:isolate;
  animation:cardDeal .52s cubic-bezier(.2,.8,.25,1) both, cardFloat 5.4s ease-in-out .7s infinite;
}
.card::before{content:"";position:absolute;inset:5px;border:1px solid rgba(255,248,224,.7);border-radius:9px;pointer-events:none;z-index:-1}
.card:nth-child(2){animation-delay:.06s}.card:nth-child(3){animation-delay:.12s}.card:nth-child(4){animation-delay:.18s}.card:nth-child(5){animation-delay:.24s}.card:nth-child(n+6){animation-delay:.3s}
@keyframes cardDeal{from{opacity:0;transform:translateY(34px) rotate(2deg) scale(.94)}to{opacity:1;transform:none}}
@keyframes cardFloat{0%,100%{translate:0 0}50%{translate:0 -2px}}
.card:hover{ transform:translateY(-4px); box-shadow:var(--shadow-deep); }
.card:focus-visible{ outline:3px solid var(--gold-bright); outline-offset:2px; }
.card .corner{ position:absolute; width:16px; height:16px; border:2px solid var(--gold); opacity:.55; }
.card .corner.tl{ top:6px; left:6px; border-right:none; border-bottom:none; }
.card .corner.tr{ top:6px; right:6px; border-left:none; border-bottom:none; }
.card .corner.bl{ bottom:6px; left:6px; border-right:none; border-top:none; }
.card .corner.br{ bottom:6px; right:6px; border-left:none; border-top:none; }

.player-card .player-icon{
  width:64px; height:64px; border-radius:50%; margin:0 auto 10px;
  background:radial-gradient(circle at 35% 30%, var(--forest), var(--forest-dark));
  display:flex; align-items:center; justify-content:center; box-shadow: inset 0 0 0 2px rgba(205,162,58,.5), var(--shadow-soft);
  font-size:2rem; line-height:1;
}
.player-card .player-name{ font-family:'Cinzel', serif; font-size:1.05rem; color:var(--ink); }
.player-card .player-meta{ font-size:.85rem; color:var(--ink-soft); margin-top:4px; }

.player-profile{
  display:flex; align-items:center; gap:18px; margin:0 0 22px;
  padding:16px 18px; border:1px solid var(--parchment-shadow); border-radius:14px;
  background:linear-gradient(180deg,rgba(255,255,255,.42),rgba(226,210,168,.52));
  box-shadow:var(--shadow-soft);
}
.player-profile-avatar{
  width:84px; height:84px; flex:0 0 84px; border-radius:50%;
  display:flex; align-items:center; justify-content:center;
  background:radial-gradient(circle at 35% 30%, var(--forest), var(--forest-dark));
  border:3px solid var(--gold); box-shadow:inset 0 0 0 2px rgba(255,255,255,.12),var(--shadow-soft);
  font-size:2.65rem; line-height:1;
}
.player-profile-main{ min-width:0; flex:1; text-align:left; }
.player-profile-name{ margin:0; color:var(--burgundy-dark); font-size:1.35rem; }
.player-profile-handle{
  margin:1px 0 3px; color:var(--gold); font-family:'Cinzel', serif; font-size:.88rem;
  letter-spacing:.05em; font-weight:600;
}
.player-profile-handle.unset{ color:var(--ink-soft); font-family:'EB Garamond', serif; font-style:italic; font-weight:400; letter-spacing:0; }
.player-profile-meta{ margin:2px 0 10px; color:var(--ink-soft); font-size:.9rem; }
.player-profile-actions{ display:flex; flex-wrap:wrap; gap:8px; }
.player-profile-actions .mini-btn{ background:rgba(255,248,234,.65); }

.emoji-grid{
  display:grid; grid-template-columns:repeat(5,1fr); gap:9px; margin:14px 0 8px;
}
.emoji-choice{
  min-height:58px; border:1px solid var(--parchment-shadow); border-radius:10px;
  background:#fff8ea; cursor:pointer; font-size:1.75rem; line-height:1;
  display:flex; align-items:center; justify-content:center; transition:.15s;
}
.emoji-choice:hover{ transform:translateY(-2px); border-color:var(--gold); box-shadow:var(--shadow-soft); }
.emoji-choice.selected{ outline:3px solid rgba(169,124,31,.32); border-color:var(--gold); background:#f8e7b8; }
.emoji-choice:focus-visible{ outline:3px solid var(--gold-bright); outline-offset:2px; }

@media (max-width:560px){
  .player-profile{ align-items:flex-start; gap:13px; padding:14px; }
  .player-profile-avatar{ width:68px; height:68px; flex-basis:68px; font-size:2.15rem; }
  .player-profile-name{ font-size:1.12rem; }
  .emoji-grid{ grid-template-columns:repeat(4,1fr); }
}

.add-card{ display:flex; flex-direction:column; align-items:center; justify-content:center; border-style:dashed; color:var(--ink-soft); min-height:150px; }
.add-card .plus{ font-size:2rem; color:var(--gold); line-height:1; margin-bottom:6px; }
.add-card:hover{ color:var(--ink); }

.character-card .portrait-frame{
  position:relative;
  width:125px;
  height:125px;
  flex:0 0 125px;
  margin:0 auto 12px;
  padding:0;
  overflow:hidden;
  border:0;
  border-radius:0;
  box-shadow:none;
  background:transparent;
  line-height:0;
}
.character-card .portrait-frame .portrait-img{
  position:static !important;
  display:block;
  width:125px !important;
  height:125px !important;
  margin:0 !important;
  padding:0 !important;
  border:0 !important;
  object-fit:contain;
  object-position:center;
  transform:none !important;
  filter:drop-shadow(0 7px 8px rgba(40,23,12,.2));
  animation:portraitBreathe 4.2s ease-in-out infinite;
}
.character-card:nth-child(even) .portrait-img{animation-delay:-2.1s}
@keyframes portraitBreathe{0%,100%{translate:0 0}50%{translate:0 -3px}}
.character-card .character-name{ font-family:'Cinzel', serif; font-size:1.05rem; }
.character-card .character-wealth{ font-size:.9rem; color:var(--ink-soft); margin:4px 0 12px; }
.character-description{min-height:2.6em;margin:7px 2px 10px;color:#654b34;font-size:.83rem;line-height:1.3;font-style:italic}
.btn{
  font-family:'Cinzel', serif; font-size:.85rem; letter-spacing:.03em;
  border:none; border-radius:8px; padding:10px 16px; cursor:pointer; transition:.15s;
}
.btn-primary{ background:linear-gradient(180deg, var(--gold-bright), var(--gold)); color:#2E2116; box-shadow:var(--shadow-soft); }
.btn-primary:hover{ filter:brightness(1.06); transform:translateY(-1px); }
.btn-primary:disabled{ opacity:.55; cursor:default; transform:none; }
.btn-secondary{ background:transparent; border:1px solid var(--ink-soft); color:var(--ink-soft); }
.btn-secondary:hover{ background:rgba(0,0,0,.05); }
.btn-danger{ background:linear-gradient(180deg,#8a2a38, var(--burgundy-dark)); color:var(--parchment); }
.btn-block{ width:100%; }


/* ---------- management controls ---------- */
.card-actions{
  display:flex; gap:7px; justify-content:center; flex-wrap:wrap; margin-top:12px;
}
.mini-btn{
  font-family:'EB Garamond', serif; font-size:.82rem; padding:6px 10px; border-radius:7px;
  border:1px solid var(--parchment-shadow); background:rgba(255,255,255,.35); color:var(--ink-soft);
  cursor:pointer; transition:.15s;
}
.mini-btn:hover{ background:#fff3d7; color:var(--ink); }
.mini-btn.danger{ border-color:#a86a72; color:var(--burgundy); }
.manage-row{ display:flex; justify-content:center; gap:10px; flex-wrap:wrap; margin:0 0 20px; }
.inactive-list{ display:flex; flex-direction:column; gap:10px; margin:12px 0 4px; }
.inactive-item{
  display:flex; align-items:center; justify-content:space-between; gap:12px;
  padding:10px 12px; border:1px solid var(--parchment-shadow); border-radius:9px;
  background:rgba(255,255,255,.3);
}
.inactive-name{ font-weight:600; }
.inactive-meta{ color:var(--ink-soft); font-size:.84rem; }

/* ---------- bank screen: open ledger signature ---------- */
.ledger{
  display:grid; grid-template-columns:1fr 1fr; gap:0;
  background:linear-gradient(180deg,#F6ECD3,#EFE1BD);
  border-radius:14px; box-shadow:var(--shadow-deep);
  border:1px solid var(--parchment-shadow);
  overflow:hidden; position:relative;
}
.ledger::before{
  content:""; position:absolute; top:0; bottom:0; left:50%; width:26px; margin-left:-13px;
  background:linear-gradient(90deg, transparent, rgba(46,33,22,.16) 45%, rgba(46,33,22,.16) 55%, transparent);
  pointer-events:none;
}
.ledger-page{ padding:26px 24px; }
.ledger-left{ border-right:none; }
.ledger-right{ background:rgba(255,255,255,.12); max-height:560px; overflow-y:auto; }
@media (max-width:760px){
  .ledger{ grid-template-columns:1fr; }
  .ledger::before{ display:none; }
  .ledger-right{ max-height:420px; border-top:2px dashed var(--parchment-shadow); }
}
@media (max-width:480px){
  .container{ padding-left:12px; padding-right:12px; }
  .coin-pouch-row{ grid-template-columns:repeat(2,1fr); }
  .action-row{ flex-direction:column; }
  .action-row .btn{ width:100%; min-width:0; }
  .coin-inputs{ grid-template-columns:1fr; }
  .modal-actions{ flex-direction:column-reverse; }
  .modal-actions .btn{ width:100%; }
  .grid{ grid-template-columns:1fr; }
}

.bank-main{
  display:grid; grid-template-columns:minmax(155px,.8fr) minmax(245px,1.2fr);
  gap:18px; align-items:stretch;
}
.bank-character-art{
  position:relative; isolation:isolate; height:auto; min-height:430px; overflow:hidden;
  align-self:stretch;
  display:flex; align-items:center; justify-content:center;
}
.bank-character-art img{
  position:static; width:100%; height:430px; max-width:100%; max-height:430px;
  object-fit:contain; object-position:center center; display:block;
}
.bank-details{ min-width:0; display:flex; flex-direction:column; justify-content:center; }
.bank-main-text-only{ grid-template-columns:1fr; }
.bank-header{ text-align:center; margin-bottom:12px; }
.bank-name{ font-size:1.5rem; color:var(--burgundy-dark); }
.bank-total{ font-size:1rem; color:var(--ink-soft); margin-top:2px; }
.bank-total strong{ color:var(--gold-bright); background:var(--ink); padding:1px 8px; border-radius:6px; }

.coin-pouch-row{ display:grid; grid-template-columns:repeat(2,1fr); gap:10px; margin:14px 0; }
.coin-pouch{
  position:relative;overflow:hidden;background:rgba(255,255,255,.35); border:1px solid var(--parchment-shadow); border-radius:10px;
  padding:12px 6px; text-align:center;
}
.coin-pouch::after{content:"";position:absolute;top:-60%;left:-85%;width:55%;height:220%;transform:rotate(24deg);background:linear-gradient(90deg,transparent,#fff9,transparent);animation:coinShine 4.8s ease-in-out infinite}
.coin-pouch:nth-child(2)::after{animation-delay:1.1s}.coin-pouch:nth-child(3)::after{animation-delay:2.2s}.coin-pouch:nth-child(4)::after{animation-delay:3.3s}
@keyframes coinShine{0%,68%{left:-85%}88%,100%{left:145%}}
.coin-pouch.coin-ppt{background:linear-gradient(145deg,#e7edf5,#9eb2c5)}
.coin-pouch.coin-po{background:linear-gradient(145deg,#fff1a7,#d3a52d)}
.coin-pouch.coin-pl{background:linear-gradient(145deg,#f4f4f2,#a7aaae)}
.coin-pouch.coin-pc{background:linear-gradient(145deg,#e8b18a,#a85e30)}
.coin-icon{ width:34px; height:34px; margin:0 auto 6px; display:block; }
.coin-icon circle{ stroke-width:1.5; }
.coin-ppt circle{ fill:var(--ppt); stroke:var(--ppt-dark); } .coin-ppt .coin-mark{ fill:var(--ppt-dark); }
.coin-po circle{ fill:var(--po); stroke:var(--po-dark); } .coin-po .coin-mark{ fill:var(--po-dark); }
.coin-pl circle{ fill:var(--pl); stroke:var(--pl-dark); } .coin-pl .coin-mark{ fill:var(--pl-dark); }
.coin-pc circle{ fill:var(--pc); stroke:var(--pc-dark); } .coin-pc .coin-mark{ fill:var(--pc-dark); }
.coin-pouch .coin-abbr{ font-family:'Cinzel', serif; font-size:.75rem; color:var(--ink-soft); letter-spacing:.05em; }
.coin-pouch .coin-count{ font-size:1.25rem; font-weight:600; }

.action-row{ display:flex; gap:10px; flex-wrap:wrap; margin-top:6px; }
.action-row .btn{ flex:1; min-width:140px; }

@media (max-width:760px){
  .bank-main{ grid-template-columns:minmax(130px,.75fr) 1.25fr; }
}
@media (max-width:480px){
  .bank-main{ grid-template-columns:1fr; }
  .bank-character-art{ height:300px; min-height:300px; align-self:auto; }
}

.ledger-title{ font-size:1.1rem; color:var(--burgundy-dark); margin:0 0 14px; text-align:center; }
.history-item{
  border-bottom:1px dashed var(--parchment-shadow); padding:12px 4px; font-size:.92rem;
}
.history-item:last-child{ border-bottom:none; }
.history-top{ display:flex; justify-content:space-between; gap:10px; align-items:baseline; }
.history-type{ font-family:'Cinzel', serif; font-size:.82rem; letter-spacing:.02em; }
.history-type.add{ color:var(--forest); }
.history-type.spend{ color:var(--burgundy); }
.history-type.exchange{ color:var(--gold-dark, var(--gold)); }
.history-type.initial{ color:var(--ink-soft); }
.history-type.correction{ color:#8a4b12; }
.history-date{ font-size:.78rem; color:var(--ink-soft); white-space:nowrap; }
.history-delta{ margin-top:3px; }
.history-meta{ font-size:.82rem; color:var(--ink-soft); font-style:italic; margin-top:2px; }
.history-result{ font-size:.78rem; color:var(--ink-soft); margin-top:4px; }
.coin-tag{ display:inline-block; padding:0 4px; border-radius:4px; font-weight:600; }

/* ---------- modal ---------- */
#modal-overlay{
  position:fixed; inset:0; background:rgba(20,14,8,.55); display:none;
  align-items:center; justify-content:center; padding:16px; z-index:50;
}
#modal-overlay.open{ display:flex; }
.modal-card{
  background:linear-gradient(180deg,#F8EFD8,#EFE1BD);
  border-radius:14px; padding:26px 24px; max-width:440px; width:100%;
  box-shadow:var(--shadow-deep); border:1px solid var(--parchment-shadow);
  max-height:88vh; overflow-y:auto; overflow-x:hidden; box-sizing:border-box;
  animation: modalIn .25s ease;
}
@keyframes modalIn{ from{ opacity:0; transform:translateY(12px) scale(.98);} to{opacity:1; transform:none;} }
.modal-title{ font-size:1.2rem; color:var(--burgundy-dark); margin:0 0 16px; text-align:center; }
.modal-summary{ text-align:center; }
.modal-detail{ text-align:center; color:var(--ink-soft); font-size:.9rem; margin:4px 0; }
.coin-inputs{ display:grid; grid-template-columns:repeat(2,1fr); gap:10px; margin-bottom:14px; }
.coin-input{ display:flex; flex-direction:column; font-family:'Cinzel', serif; font-size:.8rem; color:var(--ink-soft); gap:5px; padding:8px; border-radius:10px; border:1px solid rgba(126,91,45,.22); box-sizing:border-box; min-width:0; }
.coin-input.coin-ppt{background:rgba(112,145,178,.16)}
.coin-input.coin-po{background:rgba(218,174,45,.18)}
.coin-input.coin-pl{background:rgba(145,151,156,.15)}
.coin-input.coin-pc{background:rgba(181,101,53,.15)}
.coin-input input, .text-input input, .select-input select{
  font-family:'EB Garamond', serif; font-size:1rem; padding:9px 10px; border-radius:8px;
  border:1px solid var(--parchment-shadow); background:#fff8ea; box-sizing:border-box; width:100%; min-width:0;
}
.text-input, .select-input{ display:block; font-size:.85rem; color:var(--ink-soft); margin-bottom:12px; }
.text-input input, .select-input select{ width:100%; margin-top:4px; }
.checkbox-input{ font-size:.88rem; color:var(--ink-soft); display:flex; align-items:center; gap:8px; margin-bottom:12px; }
.modal-actions{ display:flex; gap:10px; justify-content:flex-end; margin-top:6px; }
.modal-error{ color:var(--burgundy); font-size:.85rem; text-align:center; margin-top:10px; min-height:1em; }
.text-input textarea{width:100%;box-sizing:border-box;min-height:86px;resize:vertical;margin-top:4px;padding:10px;border:1px solid var(--parchment-shadow);border-radius:8px;background:#fff8ea;color:var(--ink);font:1rem 'EB Garamond',serif}
.text-input textarea.spend-reason{min-height:38px;max-height:150px;resize:none;overflow-x:hidden;overflow-y:auto;white-space:pre-wrap;overflow-wrap:anywhere;box-sizing:border-box;line-height:1.35}
.character-lore{margin:13px 0 4px;padding:13px 14px;border-left:4px solid var(--gold);background:rgba(255,248,234,.55);border-radius:0 9px 9px 0}
.character-lore p{margin:0}.character-lore .notes{margin-top:9px;padding-top:9px;border-top:1px dashed var(--parchment-shadow);color:var(--ink-soft);font-style:italic}

/* ---------- footer ---------- */
.site-footer{position:relative;z-index:1;margin-top:12px;padding:26px 18px 34px;text-align:center;color:#ebdcb8;background:linear-gradient(180deg,rgba(21,13,9,.93),rgba(8,6,5,.98));border-top:3px double rgba(205,162,58,.56);box-shadow:0 -8px 28px rgba(24,12,7,.25)}
.site-footer p{margin:0 0 10px;color:#cbbd9e;font-style:italic}.discord-link{display:inline-flex;align-items:center;gap:9px;padding:10px 16px;border:1px solid #8ea1ff;border-radius:9px;background:#5865f2;color:white;text-decoration:none;font-family:'Cinzel',serif;font-size:.82rem;box-shadow:0 7px 18px #0005;transition:.18s}.discord-link:hover{transform:translateY(-2px);filter:brightness(1.1)}

/* ---------- toast ---------- */
#toast-container{ position:fixed; bottom:18px; left:50%; transform:translateX(-50%); z-index:60; display:flex; flex-direction:column; gap:8px; align-items:center; }
.toast{
  font-family:'EB Garamond', serif; padding:10px 20px; border-radius:8px; color:#F8EFD8;
  box-shadow:var(--shadow-deep); animation: toastIn .25s ease, toastOut .3s ease 3.4s forwards;
}
.toast.success{ background:var(--forest-dark); }
.toast.error{ background:var(--burgundy-dark); }
@keyframes toastIn{ from{opacity:0; transform:translateY(10px);} to{opacity:1; transform:none;} }
@keyframes toastOut{ to{opacity:0; transform:translateY(10px);} }

/* ---------- vault entrance ---------- */
#vault-intro{
  position:fixed; inset:0; z-index:9999; overflow:hidden;
  display:flex; align-items:center; justify-content:center;
  background:#110d09;
  perspective:1200px;
  transition:opacity .32s ease, visibility .32s ease;
}
#vault-intro.finished{ opacity:0; visibility:hidden; pointer-events:none; }
.vault-door{
  position:absolute; top:0; bottom:0; width:51%;
  background:
    radial-gradient(circle at 50% 50%, rgba(205,162,58,.09), transparent 36%),
    repeating-linear-gradient(90deg, rgba(255,255,255,.025) 0 2px, transparent 2px 18px),
    linear-gradient(180deg,#3b3934 0%,#24231f 48%,#171713 100%);
  box-shadow:inset 0 0 0 3px #5a5142, inset 0 0 0 8px #1b1915, inset 0 0 55px rgba(0,0,0,.8);
  transition:transform 1.05s cubic-bezier(.72,.03,.24,1) .58s;
}
.vault-door::before,
.vault-door::after{
  content:""; position:absolute; width:14px; height:14px; border-radius:50%;
  background:radial-gradient(circle at 35% 30%,#b89b5e,#5f4e2f 55%,#241d12 100%);
  box-shadow:0 0 0 2px #12100c, 0 calc(100vh - 52px) 0 0 #725d37;
  top:18px;
}
.vault-door-left{ left:0; transform-origin:left center; border-right:4px solid #0d0b09; }
.vault-door-left::before{ left:18px; }
.vault-door-left::after{ right:18px; }
.vault-door-right{ right:0; transform-origin:right center; border-left:4px solid #0d0b09; }
.vault-door-right::before{ left:18px; }
.vault-door-right::after{ right:18px; }
#vault-intro.opening .vault-door-left{ transform:translateX(-101%) rotateY(7deg); }
#vault-intro.opening .vault-door-right{ transform:translateX(101%) rotateY(-7deg); }
.vault-lock{
  position:relative; z-index:3; width:min(220px,52vw); aspect-ratio:1; border-radius:50%;
  display:flex; align-items:center; justify-content:center; flex-direction:column;
  color:#ead79d; text-align:center;
  background:
    radial-gradient(circle at 50% 50%,#423b2f 0 27%,#9f8147 28% 30%,#26231d 31% 47%,#aa8b50 48% 51%,#181612 52% 100%);
  border:5px solid #b29154;
  box-shadow:0 16px 50px rgba(0,0,0,.65), inset 0 0 25px rgba(0,0,0,.75);
  transition:opacity .32s ease .42s, transform .55s cubic-bezier(.4,0,.2,1) .28s;
}
.vault-lock::before{
  content:""; position:absolute; inset:18%; border:3px dashed rgba(220,190,120,.55); border-radius:50%;
  animation:vaultDial 1.25s cubic-bezier(.25,.8,.25,1) forwards;
}
.vault-lock-mark{ font-family:'Cinzel Decorative','Cinzel',serif; font-size:2.25rem; line-height:1; color:#d8b55d; text-shadow:0 2px 5px #000; }
.vault-lock-title{ margin-top:11px; font-family:'Cinzel',serif; font-size:.72rem; letter-spacing:.12em; text-transform:uppercase; }
.vault-lock-subtitle{ margin-top:3px; font-size:.72rem; opacity:.72; font-style:italic; }
#vault-intro.opening .vault-lock{ opacity:0; transform:scale(.72) rotate(-14deg); }
.vault-light{
  position:absolute; z-index:1; inset:0;
  background:radial-gradient(circle at center,rgba(239,227,198,.95) 0%,rgba(205,162,58,.28) 18%,transparent 55%);
  opacity:0; transform:scale(.55); transition:opacity .55s ease .75s, transform .8s ease .72s;
}
#vault-intro.opening .vault-light{ opacity:1; transform:scale(1.5); }
@keyframes vaultDial{
  0%{ transform:rotate(0deg); }
  42%{ transform:rotate(105deg); }
  68%{ transform:rotate(48deg); }
  100%{ transform:rotate(155deg); }
}
@media (max-width:560px){
  .vault-lock{ width:min(176px,54vw); }
  .vault-lock-mark{ font-size:1.8rem; }
}
@media (prefers-reduced-motion: reduce){
  #vault-intro{ display:none !important; }
  .vault-lock::before{ animation:none !important; }
}

/* ---------- loading ---------- */
#loading-screen{ display:flex; flex-direction:column; align-items:center; justify-content:center; min-height:60vh; gap:14px; }
.spinner-coin{
  width:46px; height:46px; border-radius:50%;
  background:radial-gradient(circle at 35% 30%, var(--gold-bright), var(--gold-dark, var(--gold)));
  box-shadow:inset 0 0 0 3px rgba(74,18,28,.4);
  animation: spin 1.1s linear infinite;
}
@media (prefers-reduced-motion: reduce){ .spinner-coin{ animation:none; } }
@keyframes spin{ to{ transform:rotateY(360deg); } }
.loading-text{ color:var(--ink-soft); font-style:italic; }

@media (prefers-reduced-motion: reduce){
  .screen, .card, .modal-card, .seal, .portrait-img, .coin-pouch::after{ animation:none !important; transition:none !important; }
}

.visually-hidden{ position:absolute; width:1px; height:1px; overflow:hidden; clip:rect(0,0,0,0); }
.player-icon img{width:100%;height:100%;object-fit:cover;border-radius:50%;display:block}
.session-bar{
  position:relative;
  display:flex;align-items:center;justify-content:center;
  margin:0;padding:7px 14px;
  min-height:39px;
  color:#e8dbc0;font-size:.86rem;
  background:linear-gradient(90deg,#15100d,#24170f,#15100d);
  border-bottom:1px solid rgba(205,162,58,.38);
  box-shadow:0 5px 14px rgba(0,0,0,.22);
}
.session-center{
  display:flex;gap:12px;align-items:center;justify-content:center;flex-wrap:wrap;
}
.session-back{
  position:absolute;
  left:16px;
  top:50%;
  transform:translateY(-50%);
  white-space:nowrap;
}
.session-bar a{color:#e8c96f;text-decoration:none}
.session-bar a:hover{text-decoration:underline}

.player-icon,.player-profile-avatar{overflow:hidden}
.player-icon img,.player-profile-avatar img{display:block;width:100%;height:100%;object-fit:cover;object-position:center}
.icon-bg-row{display:flex;align-items:center;justify-content:center;gap:16px;margin:16px 0 8px}
.icon-bg-row input[type="color"]{width:70px;height:44px;padding:2px;border:1px solid var(--parchment-shadow);border-radius:8px;background:#fff8ea;cursor:pointer}
.icon-preview{
  width:78px;height:78px;border-radius:50%;display:grid;place-items:center;
  border:3px solid var(--gold);box-shadow:var(--shadow-soft);overflow:hidden
}
.icon-preview img{width:100%;height:100%;display:block}
.history-pagination{
  display:flex;align-items:center;justify-content:center;gap:8px;flex-wrap:wrap;
  padding-top:14px;margin-top:6px;border-top:1px dashed var(--parchment-shadow)
}
.history-pagination button{
  min-width:36px;padding:6px 9px;border:1px solid var(--parchment-shadow);border-radius:7px;
  background:#fff7e5;color:var(--ink);cursor:pointer;font-family:'EB Garamond',serif
}
.history-pagination button.current{background:var(--burgundy-dark);color:var(--parchment);border-color:var(--burgundy-dark)}
.history-pagination button:disabled{opacity:.45;cursor:default}
.history-page-label{font-size:.82rem;color:var(--ink-soft)}
.transfer-note{margin:-2px 0 12px;text-align:center;color:var(--ink-soft);font-size:.86rem;font-style:italic}
@media(max-width:680px){
  .container{margin:10px 8px 18px;padding:18px 12px 56px;border-radius:16px}
  .session-bar{
    display:grid;
    grid-template-columns:1fr;
    justify-items:center;
    gap:4px;
    padding:7px 10px;
    font-size:.8rem;
  }
  .session-back{
    position:static;
    transform:none;
    justify-self:start;
  }
  .session-center{gap:9px}
}

.fatal-error{max-width:650px;margin:40px auto;padding:18px;border:1px solid #9d4652;background:#f4d8d8;border-radius:10px;color:#4a121c}


.read-only-note{display:inline-flex;padding:6px 9px;border:1px solid #aa8c56;background:#e2cf9d;color:#765a37;border-radius:6px;font-size:.78rem;font-style:italic}

/* Mega mejoras · confirmación visual e imágenes administrables */
.transfer-confirm-card{display:grid;grid-template-columns:1fr auto 1fr;align-items:center;gap:14px;margin:16px 0;padding:14px;border:1px solid #a8854c;background:linear-gradient(135deg,#f2e1b9,#d9bf87);box-shadow:inset 0 0 0 2px #fff4d166;color:#39281b}
.transfer-confirm-card>div{display:grid;gap:3px}.transfer-confirm-card small{font-size:.66rem;letter-spacing:.1em;color:#84663e;font-weight:800}.transfer-confirm-card strong{font-family:Cinzel,Georgia,serif;font-size:1rem}.transfer-confirm-card em{font-size:.74rem;color:#765e43}.transfer-confirm-arrow{font-size:2rem;color:#7a2f32;font-weight:800}
.transfer-confirm-amount{padding:10px 13px;margin:0 0 12px;text-align:center;border:1px solid #8c6b3a;background:#3a2417;color:#f4dca5;font-family:Cinzel,Georgia,serif;font-weight:700;letter-spacing:.04em}
.transfer-confirm-check{display:flex;align-items:flex-start;gap:9px;padding:10px 11px;margin:12px 0;border:1px solid #9c7c4e;background:#efe0be;color:#4b3623;cursor:pointer}.transfer-confirm-check input{width:18px;height:18px;margin-top:2px;accent-color:#6f2c31}.transfer-confirm-check span{line-height:1.25}
.admin-image-fields{margin-top:5px;padding:10px;border:1px dashed #9d7945;background:#ead9b45c}.admin-image-fields .modal-detail{margin-top:0}
.text-input small{display:block;margin-top:4px;color:#836d51;font-size:.72rem;font-style:italic}
@media(max-width:560px){.transfer-confirm-card{grid-template-columns:1fr;text-align:center}.transfer-confirm-arrow{transform:rotate(90deg);line-height:1}}


/* =========================================================
   NewBanca 2.0 · layout compacto, navegación lateral y UX
   ========================================================= */
.new-topbar-inner{
  max-width:1320px; min-height:62px; padding:8px 18px;
  grid-template-columns:minmax(150px,1fr) auto minmax(150px,1fr); gap:14px;
}
.topbar{border-bottom:2px solid rgba(205,162,58,.6);overflow:visible}
.compact-brand{display:flex;align-items:center;justify-content:center;gap:11px;min-width:0}
.compact-seal{width:38px;height:38px;flex:0 0 38px;box-shadow:0 4px 12px #0005,0 0 0 1px rgba(205,162,58,.72)}
.new-topbar-inner .app-title{font-size:clamp(1.15rem,2.15vw,1.72rem);white-space:nowrap;margin:0}
.topbar-back-link{justify-self:start;display:inline-flex;align-items:center;gap:5px;color:#ead39a;text-decoration:none;font-family:'Cinzel',serif;font-size:.83rem;font-weight:600;white-space:nowrap}
.topbar-back-link:hover{color:#fff0bd}
.account-slot{justify-self:end;min-width:0;display:flex;align-items:center;justify-content:flex-end;position:relative;z-index:5}
.header-login{display:inline-flex;align-items:center;padding:7px 11px;border:1px solid rgba(205,162,58,.45);border-radius:8px;color:#f0d993;text-decoration:none;font-family:'Cinzel',serif;font-size:.78rem}
/* Evita que una regla de display haga reaparecer controles con [hidden]. */
.header-login[hidden],.user-menu[hidden],#sidebar-add-player[hidden],#sidebar-admin-tools[hidden],#special-players-section[hidden]{display:none!important}
.user-menu{position:relative;z-index:6}
.user-menu summary{list-style:none;display:flex;align-items:center;gap:8px;cursor:pointer;color:#f4e7c5;font-family:'Cinzel',serif;font-size:.8rem;user-select:none}
.user-menu summary::-webkit-details-marker{display:none}
.header-user-avatar{width:34px;height:34px;flex:0 0 34px;border-radius:50%;overflow:hidden;border:1px solid rgba(205,162,58,.78);background:var(--forest);box-shadow:0 3px 10px #0006}
.header-user-avatar img{display:block;width:100%;height:100%;object-fit:cover}
.user-chevron{font-size:.9rem;color:#d7b85f}
.user-menu-panel{position:absolute;right:0;top:calc(100% + 10px);min-width:168px;padding:7px;background:#21160f;border:1px solid rgba(205,162,58,.48);border-radius:10px;box-shadow:0 16px 34px #0008;z-index:180}
.user-menu-panel a{display:block;padding:8px 10px;border-radius:7px;color:#eadbb8;text-decoration:none;font-size:.88rem}
.user-menu-panel a:hover{background:rgba(205,162,58,.12);color:#fff0c5}

.new-container{max-width:1320px;margin:14px auto 24px;padding:0;border-radius:18px;overflow:hidden;background:linear-gradient(145deg,rgba(239,227,198,.97),rgba(211,188,137,.95))}
.banca-layout{display:grid;grid-template-columns:225px minmax(0,1fr);min-height:650px}
.players-sidebar{padding:16px 12px 18px;background:linear-gradient(180deg,rgba(248,237,211,.92),rgba(224,205,163,.92));border-right:1px solid rgba(137,100,43,.45);box-shadow:8px 0 24px rgba(70,45,23,.08);min-width:0}
.sidebar-heading{display:flex;align-items:center;justify-content:space-between;gap:10px;padding:0 5px 10px}
.sidebar-kicker{display:block;color:var(--gold);font-family:'Cinzel',serif;font-size:.62rem;text-transform:uppercase;letter-spacing:.16em}
.sidebar-heading h2{margin:0;color:var(--burgundy-dark);font-size:1.08rem}
.sidebar-icon-btn{width:30px;height:30px;border-radius:50%;border:1px solid #9b742d;background:#f6e6bc;color:#6e4c15;font:700 1.15rem/1 'Cinzel',serif;cursor:pointer}
.sidebar-icon-btn:hover{background:#fff0c8;transform:translateY(-1px)}
.sidebar-list{display:flex;flex-direction:column;gap:5px}
.sidebar-player{display:grid;grid-template-columns:38px minmax(0,1fr) auto;align-items:center;gap:9px;width:100%;padding:6px 7px;border:1px solid transparent;border-radius:9px;background:transparent;color:var(--ink);cursor:pointer;text-align:left;font-family:'EB Garamond',serif;transition:.14s}
.sidebar-player:hover{background:rgba(255,249,232,.58);border-color:rgba(155,116,45,.28)}
.sidebar-player.active{background:linear-gradient(90deg,#ead49d,#f4e6bf);border-color:#b78b36;box-shadow:0 3px 10px rgba(80,50,20,.12)}
.sidebar-player-icon{width:36px;height:36px;border-radius:50%;overflow:hidden;border:2px solid #a97c1f;box-shadow:0 2px 7px rgba(46,33,22,.18);display:grid;place-items:center;background:var(--icon-bg,#22402F)}
.sidebar-player-icon img{width:100%;height:100%;display:block;object-fit:cover}
.sidebar-player-copy{min-width:0}
.sidebar-player-name{display:block;font-family:'Cinzel',serif;font-size:.77rem;font-weight:600;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}
.sidebar-player-meta{display:block;color:#715b43;font-size:.72rem;line-height:1.15;margin-top:1px}
.sidebar-player-arrow{color:#947132;font-size:.9rem}
.sidebar-divider{display:flex;align-items:center;gap:9px;margin:16px 4px 8px;color:#806842;font-family:'Cinzel',serif;font-size:.62rem;text-transform:uppercase;letter-spacing:.12em}
.sidebar-divider::before,.sidebar-divider::after{content:"";height:1px;background:rgba(128,104,66,.35);flex:1}
.special-list .sidebar-player{background:rgba(89,70,49,.055)}
.special-list .sidebar-player.active{background:linear-gradient(90deg,#d9c29c,#eee0c2)}
.sidebar-tools{margin-top:14px;padding-top:10px;border-top:1px dashed rgba(128,104,66,.4)}
.sidebar-tool-btn{width:100%;padding:7px 8px;border:1px solid rgba(128,104,66,.35);border-radius:8px;background:rgba(255,255,255,.28);color:#6c583e;font-family:'EB Garamond',serif;cursor:pointer}
.sidebar-tool-btn:hover{background:#fff3d8}

.banca-workspace{padding:20px 22px 34px;min-width:0;background:linear-gradient(180deg,rgba(255,250,235,.35),rgba(255,255,255,.08))}
.workspace-hero{display:flex;align-items:center;gap:14px;margin-bottom:17px;padding:3px 0 15px;border-bottom:1px solid rgba(128,104,66,.28)}
.workspace-avatar{width:58px;height:58px;flex:0 0 58px;border-radius:50%;overflow:hidden;border:2px solid var(--gold);background:var(--icon-bg,#22402F);box-shadow:var(--shadow-soft)}
.workspace-avatar img{display:block;width:100%;height:100%;object-fit:cover}
.workspace-heading{min-width:0;flex:1}
.workspace-heading h2{margin:0;color:var(--burgundy-dark);font-size:clamp(1.2rem,2vw,1.65rem)}
.workspace-handle{color:var(--gold);font-family:'Cinzel',serif;font-size:.75rem;margin-top:1px}
.workspace-meta{color:var(--ink-soft);font-size:.82rem;margin-top:3px}
.workspace-actions{display:flex;align-items:center;justify-content:flex-end;gap:7px;flex-wrap:wrap}
.workspace-actions .btn{padding:8px 12px;font-size:.72rem}
.limit-note{display:inline-flex;align-items:center;padding:7px 10px;border:1px solid rgba(110,31,44,.28);border-radius:8px;background:rgba(110,31,44,.06);color:#6e1f2c;font-size:.78rem;font-weight:600}
.profile-tools{display:flex;gap:5px;flex-wrap:wrap;margin-top:8px}
.profile-tools .mini-btn{padding:4px 8px;font-size:.73rem;background:rgba(255,255,255,.34)}
.characters-grid{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:14px}
.characters-grid .character-card{margin:0;min-height:205px;padding:13px;text-align:left;animation:cardDeal .35s ease both;cursor:pointer}
.character-card-main{display:grid;grid-template-columns:112px minmax(0,1fr);gap:13px;align-items:start}
.characters-grid .character-card .portrait-frame{width:112px;height:112px;flex-basis:112px;margin:0}
.characters-grid .character-card .portrait-frame .portrait-img{width:112px !important;height:112px !important}
.character-card-copy{min-width:0}
.characters-grid .character-name{font-size:1rem;margin:0 0 2px;color:var(--burgundy-dark)}
.characters-grid .character-wealth{font-size:.78rem;margin:0 0 7px}
.characters-grid .mini-coins{margin:6px 0 7px;gap:4px}
.characters-grid .mini-coin{font-size:.62rem;padding:3px 1px}
.characters-grid .mini-coin strong{font-size:.77rem}
.characters-grid .character-description{min-height:0;margin:5px 0 0;font-size:.76rem;display:-webkit-box;-webkit-line-clamp:2;-webkit-box-orient:vertical;overflow:hidden}
.character-card-footer{display:flex;align-items:center;justify-content:space-between;gap:8px;margin-top:10px;padding-top:9px;border-top:1px solid rgba(91,70,50,.16)}
.character-card-footer .btn{padding:7px 11px;font-size:.7rem}
.character-card-footer .card-actions{margin:0;justify-content:flex-end}
.character-card.text-only{min-height:128px}
.character-card.text-only .character-card-main{grid-template-columns:1fr}
.character-status-badge{display:inline-flex;padding:3px 7px;border-radius:999px;background:rgba(91,70,50,.1);border:1px solid rgba(91,70,50,.18);font-size:.68rem;color:#6f5940;margin-bottom:6px}
.move-special-btn{border-color:#92713f;color:#5d482d}
.workspace-back-row{display:flex;margin-bottom:12px}

/* Un único medallón: el fondo lo controla el contenedor, no un círculo dentro de otro. */
.player-icon,.player-profile-avatar,.workspace-avatar,.sidebar-player-icon,.header-user-avatar{background:var(--icon-bg,#22402F)}

@media(max-width:980px){
  .characters-grid{grid-template-columns:1fr}
  .banca-layout{grid-template-columns:205px minmax(0,1fr)}
}
@media(max-width:760px){
  .new-topbar-inner{grid-template-columns:auto 1fr auto;min-height:56px;padding:7px 10px}
  .compact-seal{display:none}
  .new-topbar-inner .app-title{font-size:1rem}
  .topbar-back-link span{display:none}
  .topbar-back-link{font-size:1.15rem}
  .user-menu summary #session-name{display:none}
  .banca-layout{display:block;min-height:0}
  .players-sidebar{border-right:0;border-bottom:1px solid rgba(137,100,43,.45);padding:10px}
  .sidebar-heading{padding-bottom:7px}
  .sidebar-heading h2{font-size:.95rem}
  .sidebar-list{flex-direction:row;overflow-x:auto;gap:7px;padding-bottom:3px;scrollbar-width:thin}
  .sidebar-player{flex:0 0 auto;width:auto;min-width:124px;grid-template-columns:32px minmax(64px,1fr);padding:5px 7px}
  .sidebar-player-icon{width:30px;height:30px}
  .sidebar-player-arrow{display:none}
  .sidebar-special{margin-top:7px}
  .sidebar-divider{justify-content:flex-start;margin:7px 2px 5px}
  .sidebar-divider::before,.sidebar-divider::after{display:none}
  .sidebar-tools{display:none !important}
  .banca-workspace{padding:15px 12px 28px}
  .workspace-hero{align-items:flex-start;flex-wrap:wrap}
  .workspace-avatar{width:48px;height:48px;flex-basis:48px}
  .workspace-actions{width:100%;justify-content:flex-start;padding-left:62px;margin-top:-5px}
  .new-container{margin:8px 7px 16px;border-radius:14px}
}
@media(max-width:520px){
  .character-card-main{grid-template-columns:86px minmax(0,1fr);gap:10px}
  .characters-grid .character-card .portrait-frame{width:86px;height:86px}
  .characters-grid .character-card .portrait-frame .portrait-img{width:86px !important;height:86px !important}
  .characters-grid .mini-coins{grid-template-columns:repeat(2,1fr)}
  .character-card-footer{align-items:flex-start;flex-direction:column}
  .workspace-actions{padding-left:0}
}

/* =========================================================
   NewBanca 2.3 · mini hub de entrada
   ========================================================= */
.banca-layout[hidden],.bank-hub[hidden]{display:none!important}
.bank-hub{
  min-height:650px;
  padding:clamp(30px,5vw,64px) clamp(18px,5vw,70px);
  display:flex;align-items:center;justify-content:center;
  background:
    radial-gradient(circle at 50% 8%,rgba(205,162,58,.16),transparent 34%),
    linear-gradient(180deg,rgba(255,250,235,.42),rgba(255,255,255,.08));
}
.bank-hub-inner{width:min(900px,100%);text-align:center}
.bank-hub-mark{
  width:66px;height:66px;margin:0 auto 13px;border-radius:50%;display:grid;place-items:center;
  border:2px solid var(--gold);background:linear-gradient(145deg,var(--forest),var(--forest-dark));
  color:#f6e8c8;font-family:'Cinzel',serif;font-weight:700;font-size:1.05rem;letter-spacing:.06em;
  box-shadow:0 9px 22px rgba(46,33,22,.25),inset 0 0 0 3px rgba(255,255,255,.06)
}
.bank-hub-kicker{margin:0 0 5px;color:var(--gold);font-family:'Cinzel',serif;font-size:.69rem;text-transform:uppercase;letter-spacing:.17em}
.bank-hub-title{margin:0;color:var(--burgundy-dark);font-size:clamp(1.75rem,4vw,2.65rem)}
.bank-hub-subtitle{max-width:610px;margin:8px auto 25px;color:var(--ink-soft);font-size:1rem}
.bank-hub-actions{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:15px;text-align:left}
.hub-action-card{
  appearance:none;width:100%;min-height:170px;padding:20px;border:1px solid rgba(155,116,45,.52);border-radius:15px;
  background:linear-gradient(145deg,rgba(255,250,235,.86),rgba(226,210,168,.64));
  color:var(--ink);box-shadow:0 8px 22px rgba(46,33,22,.14);cursor:pointer;font:inherit;text-align:left;
  transition:transform .16s ease,box-shadow .16s ease,border-color .16s ease
}
.hub-action-card:hover{transform:translateY(-3px);box-shadow:0 13px 28px rgba(46,33,22,.2);border-color:var(--gold)}
.hub-action-card:focus-visible{outline:3px solid var(--gold-bright);outline-offset:3px}
.hub-action-card.primary{background:linear-gradient(145deg,rgba(248,231,187,.96),rgba(218,188,119,.82));border-color:#a97c1f}
.hub-action-card[disabled]{opacity:.58;cursor:not-allowed;transform:none;box-shadow:0 5px 14px rgba(46,33,22,.1)}
.hub-action-top{display:flex;align-items:center;gap:12px;margin-bottom:14px}
.hub-action-icon{
  width:48px;height:48px;flex:0 0 48px;border-radius:12px;display:grid;place-items:center;
  background:rgba(34,64,47,.1);border:1px solid rgba(34,64,47,.18);color:var(--forest);font-size:1.45rem
}
.hub-action-card.primary .hub-action-icon{background:var(--forest);color:#f5dfaa;border-color:#182f22}
.hub-action-copy{min-width:0;flex:1}
.hub-action-title{display:block;font-family:'Cinzel',serif;font-weight:700;color:var(--burgundy-dark);font-size:1.02rem}
.hub-action-meta{display:block;margin-top:2px;color:#705a41;font-size:.82rem}
.hub-action-description{display:block;color:var(--ink-soft);font-size:.88rem;line-height:1.35}
.hub-action-cta{display:flex;align-items:center;justify-content:space-between;margin-top:14px;padding-top:11px;border-top:1px solid rgba(91,70,50,.17);font-family:'Cinzel',serif;font-size:.72rem;color:#765615;text-transform:uppercase;letter-spacing:.07em}
.bank-hub-summary{display:flex;justify-content:center;gap:10px;flex-wrap:wrap;margin:19px 0 0;color:#765f45;font-size:.77rem}
.bank-hub-summary span{padding:5px 9px;border:1px solid rgba(128,104,66,.22);border-radius:999px;background:rgba(255,255,255,.22)}
.bank-hub-signature{margin:19px 0 0;color:#816b4b;font-size:.78rem;font-style:italic}
.sidebar-home-btn{
  width:100%;display:flex;align-items:center;justify-content:center;gap:7px;margin:0 0 11px;padding:7px 9px;
  border:1px solid rgba(128,104,66,.28);border-radius:8px;background:rgba(255,255,255,.22);color:#715b43;
  font-family:'EB Garamond',serif;font-size:.78rem;cursor:pointer
}
.sidebar-home-btn:hover{background:#fff3d8;color:var(--ink)}
.explore-empty{min-height:500px;display:grid;place-items:center;text-align:center;padding:30px}
.explore-empty-inner{max-width:480px}
.explore-empty-icon{width:64px;height:64px;margin:0 auto 14px;border-radius:50%;display:grid;place-items:center;background:rgba(34,64,47,.09);border:1px solid rgba(34,64,47,.18);color:var(--forest);font-size:1.7rem}
.explore-empty h2{margin:0;color:var(--burgundy-dark);font-size:1.45rem}
.explore-empty p{margin:8px auto 0;color:var(--ink-soft);font-size:.92rem}
@media(max-width:760px){
  .bank-hub{min-height:540px;padding:30px 14px}
  .bank-hub-actions{grid-template-columns:1fr}
  .hub-action-card{min-height:145px}
  .sidebar-home-btn{width:auto;display:inline-flex;margin:0 0 7px}
  .explore-empty{min-height:330px}
}


/* =========================================================
   NewBanca 2.4 · hub visual refinado + footer al fondo
   ========================================================= */
html{min-height:100%}
body{display:flex;flex-direction:column;min-height:100vh}
.sticky-shell{flex:0 0 auto}
.new-container{
  flex:1 0 auto;
  width:min(calc(100% - 28px),1320px);
  position:relative;
}
.site-footer{
  flex:0 0 auto;
  margin-top:auto;
  padding:18px 18px 22px;
}

.bank-hub{
  position:relative;
  isolation:isolate;
  overflow:hidden;
  min-height:clamp(570px,68vh,720px);
  padding:clamp(34px,5vw,68px) clamp(18px,5vw,72px);
  background:
    radial-gradient(circle at 50% 0%,rgba(255,246,211,.76),transparent 34%),
    radial-gradient(circle at 12% 88%,rgba(110,31,44,.08),transparent 31%),
    radial-gradient(circle at 88% 82%,rgba(34,64,47,.10),transparent 34%),
    linear-gradient(150deg,rgba(250,239,210,.93),rgba(220,199,149,.86));
}
.bank-hub::before{
  content:"PF";
  position:absolute;
  z-index:-1;
  left:50%;top:50%;
  transform:translate(-50%,-47%);
  font-family:'Cinzel Decorative','Cinzel',serif;
  font-size:clamp(13rem,28vw,24rem);
  line-height:1;
  color:rgba(94,64,32,.035);
  letter-spacing:-.09em;
  pointer-events:none;
}
.bank-hub::after{
  content:"";
  position:absolute;inset:18px;
  z-index:-1;pointer-events:none;
  border:1px solid rgba(151,111,45,.24);
  border-radius:14px;
  box-shadow:inset 0 0 0 4px rgba(255,249,229,.26);
}
.bank-hub-inner{width:min(980px,100%);position:relative;z-index:1}
.bank-hub-mark{
  width:78px;height:78px;margin:0 auto 15px;
  border:1px solid #d0aa4b;
  outline:1px solid rgba(91,60,24,.36);outline-offset:4px;
  background:
    radial-gradient(circle at 36% 28%,rgba(255,255,255,.16),transparent 24%),
    linear-gradient(145deg,#31523d,#182d21 68%,#102219);
  color:#f7e8bd;
  font-size:1.15rem;
  box-shadow:0 13px 30px rgba(47,31,18,.27),inset 0 0 0 4px rgba(205,162,58,.13);
}
.bank-hub-kicker{font-size:.66rem;letter-spacing:.22em;margin-bottom:7px}
.bank-hub-title{
  font-family:'Cinzel Decorative','Cinzel',serif;
  font-size:clamp(1.9rem,4vw,2.85rem);
  text-shadow:0 1px 0 rgba(255,255,255,.44);
}
.bank-hub-subtitle{max-width:660px;margin:9px auto 8px;font-size:1rem}
.bank-hub-welcome{
  margin:0 auto 25px;
  color:#7b6446;
  font-size:.86rem;
  font-style:italic;
}
.hub-ornament{
  width:min(380px,74%);margin:0 auto 21px;
  display:flex;align-items:center;gap:10px;color:#9d7732;
}
.hub-ornament span{height:1px;flex:1;background:linear-gradient(90deg,transparent,rgba(157,119,50,.65))}
.hub-ornament span:last-child{background:linear-gradient(90deg,rgba(157,119,50,.65),transparent)}
.hub-ornament b{font-size:.66rem;font-weight:400}
.bank-hub-actions{gap:18px}
.hub-action-card{
  position:relative;overflow:hidden;
  min-height:196px;padding:22px 22px 19px;
  border-radius:16px;
  border:1px solid rgba(123,87,35,.38);
  box-shadow:0 11px 28px rgba(49,32,18,.13),inset 0 0 0 1px rgba(255,255,255,.38);
}
.hub-action-card::before{
  content:"";position:absolute;left:0;right:0;top:0;height:3px;
  background:linear-gradient(90deg,transparent,var(--gold),transparent);opacity:.72;
}
.hub-action-card::after{
  content:"";position:absolute;width:145px;height:145px;border-radius:50%;right:-70px;bottom:-82px;
  border:1px solid rgba(169,124,31,.14);box-shadow:0 0 0 13px rgba(169,124,31,.035);pointer-events:none;
}
.hub-action-card.primary{
  border-color:#8d6b28;
  background:
    radial-gradient(circle at 88% 15%,rgba(205,162,58,.17),transparent 31%),
    linear-gradient(145deg,#294a37,#1a3325 62%,#172a20);
  box-shadow:0 14px 32px rgba(28,43,32,.26),inset 0 0 0 1px rgba(238,207,128,.1);
}
.hub-action-card.primary::before{background:linear-gradient(90deg,transparent,#e0bd63,transparent);opacity:.95}
.hub-action-card:hover{transform:translateY(-4px);box-shadow:0 17px 34px rgba(46,33,22,.22)}
.hub-action-eyebrow{
  display:inline-block;margin-bottom:12px;
  font-family:'Cinzel',serif;font-size:.58rem;font-weight:700;text-transform:uppercase;letter-spacing:.15em;
  color:#98742d;
}
.hub-action-card.primary .hub-action-eyebrow{color:#d9ba69}
.hub-action-top{margin-bottom:12px}
.hub-action-icon{
  width:58px;height:58px;flex:0 0 58px;border-radius:50%;
  background:rgba(65,49,30,.06);border:1px solid rgba(125,91,39,.24);font-size:1.35rem;
  box-shadow:inset 0 0 0 3px rgba(255,255,255,.26);
}
.hub-action-icon svg{width:27px;height:27px;display:block;fill:none;stroke:currentColor;stroke-width:1.8;stroke-linecap:round;stroke-linejoin:round}
.hub-own-icon{overflow:hidden;padding:0;background:var(--icon-bg,#22402F)!important;border:2px solid #d2ad54!important;box-shadow:0 5px 14px #0004,inset 0 0 0 2px rgba(255,255,255,.18)!important}
.hub-own-icon img{width:100%;height:100%;display:block;object-fit:cover}
.hub-action-title{font-size:1.12rem}
.hub-action-meta{font-size:.8rem}
.hub-action-description{font-size:.9rem;line-height:1.38}
.hub-action-cta{margin-top:16px;padding-top:12px}
.hub-action-card.primary .hub-action-title{color:#f6e8c8}
.hub-action-card.primary .hub-action-meta{color:#d8c39a}
.hub-action-card.primary .hub-action-description{color:#eadfca}
.hub-action-card.primary .hub-action-cta{color:#e0be69;border-top-color:rgba(240,216,157,.18)}
.hub-action-card.primary .hub-action-icon{color:#f4dc9d}
.bank-hub-summary{margin-top:21px;gap:9px}
.bank-hub-summary span{
  display:inline-flex;align-items:baseline;gap:5px;
  padding:6px 11px;border-color:rgba(128,104,66,.24);background:rgba(255,250,235,.42);
  box-shadow:inset 0 1px 0 rgba(255,255,255,.45);
}
.bank-hub-summary strong{font-family:'Cinzel',serif;color:#6e4e1e;font-size:.8rem}
.bank-hub-signature{margin-top:17px;letter-spacing:.025em}

@media(max-width:760px){
  .new-container{width:calc(100% - 14px)}
  .bank-hub{min-height:0;padding:34px 14px 38px}
  .bank-hub::after{inset:9px}
  .bank-hub-mark{width:66px;height:66px}
  .bank-hub-welcome{margin-bottom:19px}
  .hub-action-card{min-height:158px;padding:18px}
  .hub-action-icon{width:50px;height:50px;flex-basis:50px}
  .site-footer{padding:16px 12px 19px}
}

</style>

</head>
<body>

<div id="vault-intro" aria-hidden="true">
  <div class="vault-light"></div>
  <div class="vault-door vault-door-left"></div>
  <div class="vault-door vault-door-right"></div>
  <div class="vault-lock">
    <div class="vault-lock-mark">PF</div>
    <div class="vault-lock-title">Bóveda Per-Favore</div>
    <div class="vault-lock-subtitle">Abriendo el tesoro...</div>
  </div>
</div>


<div class="sticky-shell">
<header class="topbar">
  <div class="topbar-inner new-topbar-inner">
    <a class="topbar-back-link" href="<?= favor_base_path() ?>" aria-label="Volver a Favor de Jack">← <span>Favor de Jack</span></a>
    <div class="compact-brand">
      <div class="seal compact-seal" aria-hidden="true"><img src="iconhand.webp" alt=""></div>
      <h1 class="app-title wordmark">Banca Per-Favore</h1>
    </div>
    <div class="account-slot">
      <a id="session-login" class="header-login" href="<?= favor_base_path() ?>login.php?next=<?= rawurlencode(favor_base_path()) ?>bancadigital%2F" hidden>Iniciar sesión</a>
      <details id="session-menu" class="user-menu" hidden>
        <summary>
          <span class="header-user-avatar"><img id="session-avatar" src="iconhand.webp" alt=""></span>
          <span id="session-name">Mi cuenta</span><span class="user-chevron">⌄</span>
        </summary>
        <div class="user-menu-panel">
          <a id="session-account" href="<?= favor_base_path() ?>cuenta.php">Mi cuenta</a>
          <a id="session-logout" href="<?= favor_base_path() ?>logout.php?next=<?= rawurlencode(favor_base_path()) ?>bancadigital%2F">Cerrar sesión</a>
        </div>
      </details>
    </div>
  </div>
</header>
</div>

<div class="container new-container">
  <div id="loading-screen">
    <div class="spinner-coin" aria-hidden="true"></div>
    <p class="loading-text">Abriendo el libro contable...</p>
  </div>

  <div id="app" style="display:none;">
    <section id="screen-hub" class="bank-hub" aria-labelledby="bank-hub-title">
      <div class="bank-hub-inner">
        <div class="bank-hub-mark" aria-hidden="true">PF</div>
        <p class="bank-hub-kicker">Tesorería del gremio</p>
        <h2 id="bank-hub-title" class="bank-hub-title">Banca Per-Favore</h2>
        <p class="bank-hub-subtitle">Administra los fondos de tus aventureros o consulta las bancas de otros jugadores.</p>
        <p id="hub-welcome" class="bank-hub-welcome">Elige cómo quieres entrar a la bóveda.</p>
        <div class="hub-ornament" aria-hidden="true"><span></span><b>◆</b><span></span></div>
        <div class="bank-hub-actions">
          <button id="hub-own-bank" class="hub-action-card primary" type="button" onclick="enterMyBank()">
            <span class="hub-action-eyebrow">Acceso personal</span>
            <span class="hub-action-top">
              <span id="hub-own-icon" class="hub-action-icon hub-own-icon" aria-hidden="true"><img id="hub-own-avatar" src="iconhand.webp" alt=""></span>
              <span class="hub-action-copy">
                <span class="hub-action-title">Mi Banca</span>
                <span id="hub-own-meta" class="hub-action-meta">Cargando tu cuenta…</span>
              </span>
            </span>
            <span id="hub-own-description" class="hub-action-description">Entra directamente a tus personajes y saldos.</span>
            <span class="hub-action-cta"><span>Abrir mi banca</span><span aria-hidden="true">→</span></span>
          </button>

          <button class="hub-action-card" type="button" onclick="explorePlayers()">
            <span class="hub-action-eyebrow">Libro del gremio</span>
            <span class="hub-action-top">
              <span class="hub-action-icon" aria-hidden="true"><svg viewBox="0 0 32 32"><circle cx="11" cy="11" r="4"></circle><circle cx="22" cy="12" r="3.5"></circle><path d="M4.5 25c.6-5 3-7.5 6.5-7.5s6 2.5 6.5 7.5"></path><path d="M18 24c.5-3.8 2.3-5.8 5-5.8 2.6 0 4.3 1.7 4.8 5.2"></path></svg></span>
              <span class="hub-action-copy">
                <span class="hub-action-title">Explorar jugadores</span>
                <span id="hub-explore-meta" class="hub-action-meta">Jugadores del gremio</span>
              </span>
            </span>
            <span class="hub-action-description">Elige a quién quieres consultar sin abrir primero la banca de otra persona.</span>
            <span class="hub-action-cta"><span>Ver jugadores</span><span aria-hidden="true">→</span></span>
          </button>
        </div>
        <div id="hub-summary" class="bank-hub-summary" aria-label="Resumen de la banca"></div>
        <p class="bank-hub-signature">Per-Favore · Sistema bancario del gremio</p>
      </div>
    </section>

    <div id="banca-layout" class="banca-layout" hidden>
      <aside class="players-sidebar" aria-label="Navegación de jugadores">
        <button class="sidebar-home-btn" type="button" onclick="goToHub()">⌂ Inicio de banca</button>
        <div class="sidebar-heading">
          <div>
            <span class="sidebar-kicker">Banca</span>
            <h2>Jugadores</h2>
          </div>
          <button id="sidebar-add-player" class="sidebar-icon-btn" type="button" onclick="showAddPlayerModal()" title="Añadir jugador" aria-label="Añadir jugador" hidden>+</button>
        </div>
        <div id="players-list" class="sidebar-list"></div>

        <div id="special-players-section" class="sidebar-special" hidden>
          <div class="sidebar-divider"><span>Archivo</span></div>
          <div id="special-players-list" class="sidebar-list special-list"></div>
        </div>

        <div id="sidebar-admin-tools" class="sidebar-tools" hidden>
          <button class="sidebar-tool-btn" type="button" onclick="showInactiveModal()">Administrar desactivados</button>
        </div>
      </aside>

      <main class="banca-workspace">
        <section id="screen-characters" class="screen">
          <div id="characters-title"></div>
          <div id="characters-grid" class="characters-grid"></div>
        </section>

        <section id="screen-bank" class="screen">
          <div class="workspace-back-row">
            <button class="back-btn" onclick="goToCharacters()">‹ Personajes del jugador</button>
          </div>
          <div id="bank-content"></div>
        </section>
      </main>
    </div>
  </div>
</div>

<footer class="site-footer">
  <p>¿Se rompió algo, desapareció una moneda o la bóveda decidió odiarte?</p>
  <a class="discord-link" href="https://discord.com/app" target="_blank" rel="noopener noreferrer" aria-label="Contactar por Discord">◈ Háblame por Discord, pelotudo</a>
</footer>

<div id="modal-overlay" role="dialog" aria-modal="true"></div>
<div id="toast-container" aria-live="polite"></div>

<script>
/* ================= data & constants ================= */
const PLAYERS_KEY = 'players';
const CHARACTERS_KEY = 'characters';
const COIN_KEYS = ['ppt','po','pl','pc'];
const COIN_META = {
  ppt:{label:'Platino', abbr:'PPT'},
  po:{label:'Oro', abbr:'PO'},
  pl:{label:'Plata', abbr:'PL'},
  pc:{label:'Cobre', abbr:'PC'}
};
const RATE = { ppt:1000, po:100, pl:10, pc:1 }; // valor en piezas de cobre
const TYPE_LABEL = {
  add:'Dinero añadido', spend:'Dinero gastado', exchange:'Cambio de monedas',
  initial:'Saldo inicial', correction:'Corrección'
};

const FANTASY_EMOJIS = [
  {emoji:'🛡️', label:'Escudo'}, {emoji:'⚔️', label:'Espadas'}, {emoji:'🗡️', label:'Daga'},
  {emoji:'🏹', label:'Arco'}, {emoji:'🪄', label:'Varita'}, {emoji:'🔮', label:'Orbe mágico'},
  {emoji:'🧙', label:'Mago'}, {emoji:'🧝', label:'Elfo'}, {emoji:'🧚', label:'Hada'},
  {emoji:'🧛', label:'Vampiro'}, {emoji:'🐉', label:'Dragón'}, {emoji:'🐲', label:'Dragón oriental'},
  {emoji:'🐺', label:'Lobo'}, {emoji:'🦇', label:'Murciélago'}, {emoji:'🦅', label:'Águila'},
  {emoji:'💀', label:'Calavera'}, {emoji:'👑', label:'Corona'}, {emoji:'🏰', label:'Castillo'},
  {emoji:'🧿', label:'Talismán'}, {emoji:'📜', label:'Pergamino'}
];
function playerEmoji(player){
  if(player && player.emoji) return player.emoji;
  const order = player && Number.isInteger(player.order) ? player.order : 0;
  return FANTASY_EMOJIS[Math.abs(order) % FANTASY_EMOJIS.length].emoji;
}
function playerHandle(player){
  const raw = player && typeof player.handle === 'string' ? player.handle.trim() : '';
  return raw.replace(/^#+/, '').trim();
}

const BUILTIN_CHARACTER_PORTRAITS = {};

const PLACEHOLDER_PORTRAIT = 'data:image/svg+xml,' + encodeURIComponent(
  '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 100 100"><rect width="100" height="100" fill="#DCC9A0"/><circle cx="50" cy="38" r="18" fill="#8a6d4a"/><path d="M18 92 Q50 58 82 92 Z" fill="#8a6d4a"/></svg>'
);

let state = {
  players: [],
  characters: [],
  screen: 'hub',
  currentPlayerId: null,
  currentCharacterId: null,
  movementsCache: {}, historyPage:{}, user:null, csrf:''
};

function isLoggedIn(){ return !!(state.user && state.user.loggedIn && state.user.role !== 'guest'); }
function isAdminUser(){ return !!(state.user && state.user.role === 'admin'); }
function canEditPlayer(playerId){ return isLoggedIn() && (isAdminUser() || state.user.playerId === playerId); }
function canEditCharacter(character){ return !!character && canEditPlayer(character.playerId); }
function requireClientLogin(){
  if(isLoggedIn()) return true;
  window.location.href='<?= favor_base_path() ?>login.php?next='+encodeURIComponent('<?= favor_base_path() ?>bancadigital/');
  return false;
}

/* ================= server API ================= */
async function apiRequest(action, payload=null, method='GET'){
  const options={method,headers:{'Accept':'application/json'}};
  let url='<?= favor_base_path() ?>bancadigital/api.php?action='+encodeURIComponent(action);
  if(method!=='GET'){
    options.headers['Content-Type']='application/json';
    options.headers['X-CSRF-Token']=state.csrf||'';
    options.body=JSON.stringify(payload||{});
  }else if(payload){ url += '&' + new URLSearchParams(payload).toString(); }
  const response=await fetch(url,options);
  const raw=await response.text();
  let data=null;
  try{ data=JSON.parse(raw); }catch(_){
    const type=response.headers.get('content-type')||'sin Content-Type';
    throw new Error(`Respuesta inválida del servidor (HTTP ${response.status}, ${type}).`);
  }
  if(response.status===401){ location.href='<?= favor_base_path() ?>bancadigital/'; throw new Error('SESSION'); }
  if(!response.ok || !data.ok) throw new Error(data.error||'No se pudo completar la operación');
  return data;
}
function uid(){
  return (crypto.randomUUID ? crypto.randomUUID() : 'id-'+Date.now()+'-'+Math.random().toString(16).slice(2));
}
function nextPlayerId(){
  const used = state.players.map(p=>{
    const m = /^J(\d{3})$/i.exec(String(p.id||''));
    return m ? Number(m[1]) : 0;
  });
  const next = Math.max(0, ...used) + 1;
  if(next > 999) throw new Error('Se alcanzó el límite de IDs J999.');
  return 'J' + String(next).padStart(3,'0');
}
function escapeHtml(str){
  return String(str==null?'':str).replace(/[&<>"']/g, ch => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[ch]));
}
function toast(type, msg){
  const el = document.createElement('div');
  el.className = 'toast ' + type;
  el.textContent = msg;
  document.getElementById('toast-container').appendChild(el);
  setTimeout(()=>el.remove(), 3900);
}
/* ================= imagenes  ================= */
function assetName(value){
  return String(value || '')
    .trim()
    .toLocaleLowerCase('es')
    .normalize('NFC')
    .replace(/[<>:"/\\|?*]/g, '');
}

const CHARACTER_FILE_ALIASES = Object.freeze({
  'aliche de fleur': 'aliche',
  'julieta ciero': 'julieta',
  'pahnaetiel': 'pan',
  'samantha masoch': 'samantha',
  'iván greco': 'ivangreco'
});

const TEXT_ONLY_PLAYERS = new Set(['npc', 'npcs', 'muertos', 'personajes fuera', 'retirados']);

function characterFileName(character){
  const normalized = assetName(character?.name);
  return CHARACTER_FILE_ALIASES[normalized] || normalized;
}

function isTextOnlyCharacter(character){
  const player = state.players.find(item => item.id === character?.playerId);
  return !!player && TEXT_ONLY_PLAYERS.has(assetName(player.name));
}

function resolveAssetSrc(src){
  const value=String(src||'').trim().replace(/\\/g, '/');
  if(!value) return '';

  // URLs externas y data URLs se respetan tal cual.
  if(/^(https?:|data:)/i.test(value)) return value;

  // Compatibilidad con rutas absolutas antiguas que apuntaban a <?= favor_base_path() ?>media/.
  // Las imágenes reales de Banca viven dentro de <?= favor_base_path() ?>bancadigital/.
  if(value.startsWith('<?= favor_base_path() ?>media/icons/')){
    return '<?= favor_base_path() ?>bancadigital/icons/'+value.slice('<?= favor_base_path() ?>media/icons/'.length);
  }
  if(value.startsWith('<?= favor_base_path() ?>media/characters/')){
    return '<?= favor_base_path() ?>bancadigital/characters/'+value.slice('<?= favor_base_path() ?>media/characters/'.length);
  }

  // Las rutas absolutas correctas se dejan intactas.
  if(value.startsWith('/')) return value;

  // La BD puede seguir guardando "media/..." para no requerir ninguna migración.
  if(value.startsWith('media/icons/')){
    return '<?= favor_base_path() ?>bancadigital/icons/'+value.slice('media/icons/'.length);
  }
  if(value.startsWith('media/characters/')){
    return '<?= favor_base_path() ?>bancadigital/characters/'+value.slice('media/characters/'.length);
  }

  // Compatibilidad con variantes históricas de ruta.
  if(value.startsWith('icons/')) return '<?= favor_base_path() ?>bancadigital/icons/'+value.slice('icons/'.length);
  if(value.startsWith('characters/')) return '<?= favor_base_path() ?>bancadigital/characters/'+value.slice('characters/'.length);
  if(value.startsWith('bancadigital/icons/')) return '<?= favor_base_path() ?>bancadigital/icons/'+value.slice('bancadigital/icons/'.length);
  if(value.startsWith('bancadigital/characters/')) return '<?= favor_base_path() ?>bancadigital/characters/'+value.slice('bancadigital/characters/'.length);

  return value;
}

function characterImagePath(character, folder){
  const player = state.players.find(item => item.id === character?.playerId);
  if(!player) return PLACEHOLDER_PORTRAIT;

  const playerFolder = assetName(player.name);
  const characterFile = characterFileName(character);
  if(!playerFolder || !characterFile) return PLACEHOLDER_PORTRAIT;

  return `<?= favor_base_path() ?>bancadigital/${folder}/${encodeURIComponent(playerFolder)}/${encodeURIComponent(characterFile)}.webp`;
}

function characterIconImgAttrs(character, eager=false){
  const src = resolveAssetSrc(character?.iconUrl) || characterImagePath(character, 'icons');
  const loading = eager ? 'eager' : 'lazy';
  const priority = eager ? 'high' : 'auto';
  return `src="${escapeHtml(src)}" loading="${loading}" decoding="async" fetchpriority="${priority}"
          onerror="imageFallback(this)"`;
}

function characterPortraitImgAttrs(character){
  const src = resolveAssetSrc(character?.portraitUrl) || characterImagePath(character, 'characters');

  return `src="${escapeHtml(src)}" decoding="async" fetchpriority="high"
          onerror="imageFallback(this)"`;
}

function imageFallback(img){
  img.onerror = null;
  img.src = PLACEHOLDER_PORTRAIT;
}

function playerIconDataUrl(player){
  const emoji = playerEmoji(player);
  const svg = `
    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 100 100">
      <rect width="100" height="100" fill="transparent"/>
      <text x="50" y="50" dy=".08em" text-anchor="middle" dominant-baseline="middle"
            font-size="54" font-family="Apple Color Emoji,Segoe UI Emoji,Noto Color Emoji,sans-serif">${emoji}</text>
    </svg>
  `;
  // encodeURIComponent no codifica comillas simples; se codifican aparte
  // porque esta URL se usa dentro de atributos.
  return `data:image/svg+xml,${encodeURIComponent(svg).replace(/'/g,'%27')}`;
}
function playerIconBg(player){
  return /^#[0-9a-f]{6}$/i.test(String(player?.iconBg||'')) ? player.iconBg : '#22402F';
}
function playerIconImgAttrs(player){
  const custom=resolveAssetSrc(player?.iconUrl);
  if(custom) return `src="${escapeHtml(custom)}" decoding="async" onerror="this.onerror=null;this.src='${playerIconDataUrl(player)}'"`;
  return `src="${playerIconDataUrl(player)}" decoding="async"`;
}

/* ================= vault entrance ================= */
function playVaultIntro(){
  const vault = document.getElementById('vault-intro');
  if(!vault) return;
  if(window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches){
    vault.remove();
    return;
  }
  requestAnimationFrame(()=>{
    setTimeout(()=>vault.classList.add('opening'), 120);
    setTimeout(()=>{
      vault.classList.add('finished');
      setTimeout(()=>vault.remove(), 360);
    }, 1820);
  });
}
playVaultIntro();

/* ================= init ================= */

async function init(){
  try{
    const data=await apiRequest('state');
    state.players=data.players||[]; state.characters=data.characters||[]; state.user=data.user||{role:'guest',loggedIn:false,displayName:'Invitado'}; state.csrf=data.csrf;
    const logged=isLoggedIn();
    document.getElementById('session-name').textContent=logged ? state.user.displayName : 'Invitado';
    document.getElementById('session-login').hidden=logged;
    document.getElementById('session-menu').hidden=!logged;
    document.getElementById('sidebar-add-player').hidden=!isAdminUser();
    document.getElementById('sidebar-admin-tools').hidden=!isAdminUser();
    if(logged){
      const avatar=document.getElementById('session-avatar');
      const ownPlayer=state.players.find(p=>p.id===state.user.playerId);
      if(avatar && ownPlayer){
        avatar.parentElement?.style.setProperty('--icon-bg',playerIconBg(ownPlayer));
        const custom=resolveAssetSrc(ownPlayer.iconUrl);
        avatar.src=custom||playerIconDataUrl(ownPlayer);
        avatar.onerror=()=>{ avatar.onerror=null; avatar.src=playerIconDataUrl(ownPlayer); };
      }
    }
    const requestedCharacter = new URLSearchParams(location.search).get('character');
    if(requestedCharacter){
      const deepCharacter=state.characters.find(c=>String(c.id)===String(requestedCharacter));
      if(deepCharacter){
        state.currentPlayerId=deepCharacter.playerId;
        state.currentCharacterId=deepCharacter.id;
        state.screen='bank';
      }
    }
    if(!state.currentPlayerId){
      // NewBanca 2.3: la entrada normal es neutra. Ningún jugador se selecciona
      // automáticamente; así cada usuario ve primero el mini hub de la Banca.
      state.currentPlayerId=null;
      state.currentCharacterId=null;
      state.screen='hub';
    }
    document.getElementById('loading-screen').style.display='none'; document.getElementById('app').style.display=''; render();
  }catch(e){
    const message = (e && e.message) ? escapeHtml(e.message) : 'No se pudo abrir el libro contable.';
    document.getElementById('loading-screen').innerHTML='<div class="fatal-error"><strong>No se pudo abrir el libro contable.</strong><br>'+message+'</div>';
  }
}
init();
async function persistPlayer(player){
  try{
    const data=await apiRequest('save_player',{player},'POST');
    if(data.player){
      const index=state.players.findIndex(item=>item.id===data.player.id);
      if(index===-1) state.players.push(data.player);
      else Object.assign(state.players[index],data.player);
    }
    return true;
  }catch(e){ toast('error',e.message); return false; }
}
async function persistCharacter(character, initialCoins=null){
  try{
    const payload={character};
    if(initialCoins) payload.initialCoins=initialCoins;
    const data=await apiRequest('save_character',payload,'POST');
    if(data.character){
      const index=state.characters.findIndex(item=>item.id===data.character.id);
      if(index===-1) state.characters.push(data.character);
      else Object.assign(state.characters[index],data.character);
      if(Array.isArray(data.movements)) state.movementsCache[data.character.id]=data.movements;
    }
    return true;
  }catch(e){ toast('error',e.message); return false; }
}
async function loadMovements(charId){
  if(state.movementsCache[charId]) return state.movementsCache[charId];
  const data=await apiRequest('movements',{character_id:charId}); state.movementsCache[charId]=data.movements||[]; return state.movementsCache[charId];
}

/* ================= money logic ================= */
function totalGold(coins){
  return coins.ppt*10 + coins.po + coins.pl/10 + coins.pc/100;
}
function fmtGold(n){
  return n.toLocaleString('es-CL', {maximumFractionDigits:2});
}
function exchangeCalc(giveType, giveAmt, receiveType){
  const copper = giveAmt * RATE[giveType];
  if(copper % RATE[receiveType] !== 0) return null;
  return copper / RATE[receiveType];
}
function readWholeNumberInput(el, {min=0, emptyAsZero=true}={}){
  const raw = String(el?.value ?? '').trim();
  if(raw==='' && emptyAsZero) return {ok:true, value:0};
  if(!/^\d+$/.test(raw)) return {ok:false, message:'Usa solamente números enteros, sin decimales ni signos.'};
  const value = Number(raw);
  if(!Number.isSafeInteger(value) || value < min) return {ok:false, message:`La cantidad mínima permitida es ${min}.`};
  return {ok:true, value};
}
function readCoinInputs(){
  const values = {};
  for(const k of COIN_KEYS){
    const result = readWholeNumberInput(document.querySelector(`[data-coin="${k}"]`));
    if(!result.ok) return {ok:false, coin:k, message:result.message};
    values[k] = result.value;
  }
  return {ok:true, values};
}
function coinListText(values){
  return COIN_KEYS.filter(k=>(values[k]||0)!==0).map(k=>`${Math.abs(values[k])} ${COIN_META[k].abbr}`).join(', ');
}
function coinsToCopper(coins){
  return COIN_KEYS.reduce((total,k)=>total + (Number(coins?.[k])||0) * RATE[k], 0);
}
function copperToCoins(total){
  total = Math.max(0, Math.trunc(total));
  const coins = {};
  for(const k of COIN_KEYS){
    coins[k] = Math.floor(total / RATE[k]);
    total %= RATE[k];
  }
  return coins;
}
function spendDeltaWithAutomaticChange(currentCoins, requestedCoins){
  const currentTotal = coinsToCopper(currentCoins);
  const requestedTotal = coinsToCopper(requestedCoins);
  if(requestedTotal > currentTotal) return {ok:false, currentTotal, requestedTotal};

  // Conserva las monedas existentes siempre que sea posible.
  // Solo rompe una moneda mayor cuando falta una denominación concreta.
  const resultCoins = {};
  for(const k of COIN_KEYS) resultCoins[k] = Number(currentCoins?.[k]) || 0;
  const lowToHigh = ['pc','pl','po','ppt'];

  for(let i=0;i<lowToHigh.length;i++){
    const coin = lowToHigh[i];
    let need = Number(requestedCoins?.[coin]) || 0;

    const direct = Math.min(resultCoins[coin], need);
    resultCoins[coin] -= direct;
    need -= direct;

    // Si falta esta moneda, rompe únicamente las monedas superiores necesarias.
    while(need > 0){
      let higherIndex = -1;
      for(let j=i+1;j<lowToHigh.length;j++){
        if(resultCoins[lowToHigh[j]] > 0){ higherIndex = j; break; }
      }
      if(higherIndex === -1) break;

      const higher = lowToHigh[higherIndex];
      resultCoins[higher] -= 1;
      // Al romper una moneda superior, convertimos su valor hasta la denominación requerida.
      resultCoins[coin] += RATE[higher] / RATE[coin];

      const use = Math.min(resultCoins[coin], need);
      resultCoins[coin] -= use;
      need -= use;
    }

    // Si no hay moneda superior, paga el resto usando monedas inferiores por valor.
    if(need > 0){
      let valueNeeded = need * RATE[coin];
      for(let j=i-1;j>=0 && valueNeeded>0;j--){
        const lower = lowToHigh[j];
        const take = Math.min(resultCoins[lower], Math.ceil(valueNeeded / RATE[lower]));
        resultCoins[lower] -= take;
        valueNeeded -= take * RATE[lower];
        if(valueNeeded < 0){
          // Devuelve el cambio sobrante sin tocar el resto del monedero.
          let change = -valueNeeded;
          for(let x=j;x>=0;x--){
            const ck = lowToHigh[x];
            const qty = Math.floor(change / RATE[ck]);
            resultCoins[ck] += qty;
            change -= qty * RATE[ck];
          }
        }
      }
      need = 0;
    }
  }

  const delta = {};
  for(const k of COIN_KEYS) delta[k] = resultCoins[k] - (Number(currentCoins?.[k])||0);
  return {ok:true, delta, resultCoins, currentTotal, requestedTotal};
}
function dateDisplay(iso){
  const d = new Date(iso);
  return d.toLocaleString('es-CL', { timeZone:'America/Santiago', day:'2-digit', month:'2-digit', year:'numeric', hour:'2-digit', minute:'2-digit' });
}

async function addMovementRecord(character,type,delta,reason,author){
  const data=await apiRequest('movement',{character_id:character.id,type,delta,reason},'POST');
  character.coins=data.character.coins; state.movementsCache[character.id]=data.movements; return data.movement;
}

/* ================= coin icon svg ================= */
function coinIconSVG(type){
  const paths = {
    ppt: '<path class="coin-mark" d="M20 10 L27 20 L20 30 L13 20 Z"/>',
    po: '<path class="coin-mark" d="M11 25 L13 14 L20 21 L27 14 L29 25 Z"/>',
    pl: '<path class="coin-mark" d="M25 11 A11 11 0 1 0 25 29 A8.5 8.5 0 1 1 25 11 Z"/>',
    pc: '<path class="coin-mark" d="M13 27 L13 22 L18 22 L18 16 L22 16 L22 22 L27 22 L27 27 Z M18 16 L18 12 L22 12 L22 16 Z"/>'
  };
  return `<svg class="coin-icon coin-${type}" viewBox="0 0 40 40" aria-hidden="true"><circle cx="20" cy="20" r="18"/>${paths[type]}</svg>`;
}

/* ================= navigation ================= */
const SPECIAL_PLAYER_LABELS = Object.freeze({
  'npc':'NPC',
  'npcs':'NPC',
  'muertos':'Muertos',
  'personajes fuera':'Retirados',
  'retirados':'Retirados'
});
function specialPlayerKey(player){ return assetName(player?.name); }
function isSpecialPlayer(player){ return Object.prototype.hasOwnProperty.call(SPECIAL_PLAYER_LABELS,specialPlayerKey(player)); }
function displayPlayerName(player){ return SPECIAL_PLAYER_LABELS[specialPlayerKey(player)] || player?.name || ''; }
function activeCharactersForPlayer(playerId){ return state.characters.filter(c=>c.playerId===playerId && c.active!==false); }
function activeCharacterCount(playerId){ return activeCharactersForPlayer(playerId).length; }
function selectedPlayer(){ return state.players.find(p=>p.id===state.currentPlayerId) || null; }

function goToHub(){
  state.currentPlayerId=null;
  state.currentCharacterId=null;
  state.screen='hub';
  render();
}
function explorePlayers(){
  state.currentPlayerId=null;
  state.currentCharacterId=null;
  state.screen='characters';
  render();
}
function enterMyBank(){
  if(!isLoggedIn()){
    requireClientLogin();
    return;
  }
  const own=state.players.find(p=>p.active!==false && p.id===state.user.playerId);
  if(!own){
    toast('error','Tu cuenta no está vinculada a un jugador activo.');
    return;
  }
  state.currentPlayerId=own.id;
  state.currentCharacterId=null;
  state.screen='characters';
  render();
}
// Compatibilidad con acciones antiguas que esperaban "volver a jugadores".
function goToPlayers(){ explorePlayers(); }
function goToCharacters(){ state.screen='characters'; state.currentCharacterId=null; render(); }
function selectPlayer(id){ state.currentPlayerId=id; state.currentCharacterId=null; state.screen='characters'; render(); }
function selectCharacter(id){ state.currentCharacterId=id; state.screen='bank'; render(); }

function showScreen(id){
  document.querySelectorAll('.screen').forEach(s=>s.classList.remove('active'));
  document.getElementById(id).classList.add('active');
}

/* ================= render ================= */
function render(){
  const hub=document.getElementById('screen-hub');
  const layout=document.getElementById('banca-layout');

  if(state.screen==='hub'){
    if(hub) hub.hidden=false;
    if(layout) layout.hidden=true;
    renderHub();
    return;
  }

  if(hub) hub.hidden=true;
  if(layout) layout.hidden=false;
  renderPlayers();
  if(state.screen==='bank') renderBank();
  else renderCharacters();
}

function renderHub(){
  const activePlayers=state.players.filter(p=>p.active!==false);
  const normalPlayers=activePlayers.filter(p=>!isSpecialPlayer(p));
  const normalIds=new Set(normalPlayers.map(p=>p.id));
  const activeAdventurers=state.characters.filter(c=>c.active!==false && normalIds.has(c.playerId));
  const own=isLoggedIn() ? normalPlayers.find(p=>p.id===state.user.playerId) : null;
  const ownCount=own ? activeCharacterCount(own.id) : 0;

  const ownButton=document.getElementById('hub-own-bank');
  const ownMeta=document.getElementById('hub-own-meta');
  const ownDescription=document.getElementById('hub-own-description');
  const exploreMeta=document.getElementById('hub-explore-meta');
  const summary=document.getElementById('hub-summary');
  const welcome=document.getElementById('hub-welcome');
  const ownAvatar=document.getElementById('hub-own-avatar');
  const ownIcon=document.getElementById('hub-own-icon');

  if(welcome){
    const name=isLoggedIn() ? (state.user.displayName||'aventurero') : null;
    welcome.textContent=name ? `Bienvenido, ${name}. Elige cómo quieres entrar a la bóveda.` : 'Elige cómo quieres entrar a la bóveda.';
  }

  if(ownAvatar && ownIcon){
    if(own){
      ownIcon.style.setProperty('--icon-bg',playerIconBg(own));
      const custom=resolveAssetSrc(own.iconUrl);
      ownAvatar.src=custom||playerIconDataUrl(own);
      ownAvatar.onerror=()=>{ ownAvatar.onerror=null; ownAvatar.src=playerIconDataUrl(own); };
    }else{
      ownIcon.style.setProperty('--icon-bg','#22402F');
      ownAvatar.onerror=null;
      ownAvatar.src='iconhand.webp';
    }
  }

  if(ownButton && ownMeta && ownDescription){
    if(!isLoggedIn()){
      ownButton.disabled=false;
      ownMeta.textContent='Inicia sesión para entrar';
      ownDescription.textContent='Accede con tu cuenta para abrir directamente tus personajes y saldos.';
      ownButton.querySelector('.hub-action-cta span:first-child').textContent='Iniciar sesión';
    }else if(own){
      ownButton.disabled=false;
      ownMeta.textContent=`${displayPlayerName(own)} · ${ownCount}/5 personajes activos`;
      ownDescription.textContent='Entra directamente a tus personajes y saldos sin pasar por la banca de otro jugador.';
      ownButton.querySelector('.hub-action-cta span:first-child').textContent='Abrir mi banca';
    }else{
      ownButton.disabled=true;
      ownMeta.textContent='Cuenta sin jugador vinculado';
      ownDescription.textContent='Puedes explorar las bancas disponibles mientras un administrador vincula tu cuenta.';
      ownButton.querySelector('.hub-action-cta span:first-child').textContent='No disponible';
    }
  }

  if(exploreMeta) exploreMeta.textContent=`${normalPlayers.length} jugador${normalPlayers.length===1?'':'es'} disponibles`;
  if(summary){
    summary.innerHTML=`<span><strong>${normalPlayers.length}</strong> jugadores del gremio</span><span><strong>${activeAdventurers.length}</strong> aventureros activos</span>`;
  }
}

let sidebarRenderSignature = null;

function sidebarPlayerButton(p){
  const count=activeCharacterCount(p.id);
  const special=isSpecialPlayer(p);
  const meta=special ? `${count} registrado${count===1?'':'s'}` : `${count}/5 activos`;
  return `<button class="sidebar-player ${p.id===state.currentPlayerId?'active':''}" data-player-id="${escapeHtml(p.id)}" type="button" onclick="selectPlayer('${p.id}')" title="${escapeHtml(displayPlayerName(p))}">
    <span class="sidebar-player-icon" style="--icon-bg:${escapeHtml(playerIconBg(p))}"><img ${playerIconImgAttrs(p)} alt=""></span>
    <span class="sidebar-player-copy"><span class="sidebar-player-name">${escapeHtml(displayPlayerName(p))}</span><span class="sidebar-player-meta">${meta}</span></span>
    <span class="sidebar-player-arrow">›</span>
  </button>`;
}

function renderPlayers(){
  const activePlayers=state.players.filter(p=>p.active!==false).sort((a,b)=>(a.order||0)-(b.order||0));
  const normal=activePlayers.filter(p=>!isSpecialPlayer(p));
  const special=activePlayers.filter(isSpecialPlayer);
  const normalList=document.getElementById('players-list');
  const specialList=document.getElementById('special-players-list');
  const specialSection=document.getElementById('special-players-section');

  // No reconstruir el sidebar al cambiar de jugador/vista.
  // Antes, innerHTML destruía y recreaba todos los <img>, provocando el "pop" visual
  // aunque el navegador ya tuviera los archivos en caché.
  const signature=JSON.stringify(activePlayers.map(p=>[
    p.id,p.name,p.order,p.emoji,p.iconBg,p.iconUrl,
    activeCharacterCount(p.id),isSpecialPlayer(p)
  ]));

  if(signature!==sidebarRenderSignature){
    if(normalList) normalList.innerHTML=normal.map(sidebarPlayerButton).join('') || '<p class="empty-note" style="font-size:.78rem;margin:8px 0;">No hay jugadores activos.</p>';
    if(specialList) specialList.innerHTML=special.map(sidebarPlayerButton).join('');
    sidebarRenderSignature=signature;
  }

  if(specialSection) specialSection.hidden=special.length===0;

  // En navegación normal sólo cambia la selección; las imágenes permanecen montadas.
  document.querySelectorAll('.sidebar-player[data-player-id]').forEach(button=>{
    button.classList.toggle('active',button.dataset.playerId===String(state.currentPlayerId));
  });
}

function characterMoveAction(c, player){
  if(!isAdminUser() || !player) return '';
  const key=specialPlayerKey(player);
  if(key==='personajes fuera' || key==='retirados'){
    const target=state.players.find(p=>p.active!==false && specialPlayerKey(p)==='muertos');
    if(target) return `<button class="mini-btn move-special-btn" type="button" data-move-target="${target.id}">Marcar muerto</button>`;
  }
  if(key==='muertos'){
    const target=state.players.find(p=>p.active!==false && ['personajes fuera','retirados'].includes(specialPlayerKey(p)));
    if(target) return `<button class="mini-btn move-special-btn" type="button" data-move-target="${target.id}">Mover a retirados</button>`;
  }
  return '';
}

function renderCharacters(){
  showScreen('screen-characters');
  const player=selectedPlayer();
  const title=document.getElementById('characters-title');
  const grid=document.getElementById('characters-grid');
  if(!player){
    const hasPlayers=state.players.some(p=>p.active!==false && !isSpecialPlayer(p));
    title.innerHTML='';
    grid.innerHTML=`<div class="explore-empty" style="grid-column:1 / -1;">
      <div class="explore-empty-inner">
        <div class="explore-empty-icon" aria-hidden="true">♟</div>
        <h2>${hasPlayers?'Selecciona un jugador':'No hay jugadores disponibles'}</h2>
        <p>${hasPlayers?'Elige un jugador en la columna izquierda para consultar sus personajes y su banca.':'Todavía no hay jugadores activos para mostrar.'}</p>
      </div>
    </div>`;
    return;
  }

  const chars=activeCharactersForPlayer(player.id).sort((a,b)=>(a.order||0)-(b.order||0));
  const special=isSpecialPlayer(player);
  const wealth=chars.reduce((sum,c)=>sum+totalGold(c.coins),0);
  const canAdd=canEditPlayer(player.id) && (special || chars.length<5);
  const limitReached=!special && chars.length>=5;
  const heading=special ? displayPlayerName(player) : `Personajes de ${escapeHtml(player.name)}`;
  const subtitle=special
    ? (specialPlayerKey(player)==='muertos' ? 'Archivo de personajes fallecidos.' : ['personajes fuera','retirados'].includes(specialPlayerKey(player)) ? 'Personajes retirados o fuera de actividad.' : 'Personajes no jugadores de la campaña.')
    : `${chars.length}/5 personajes activos · ${fmtGold(wealth)} PO en total`;

  title.innerHTML=`
    <div class="workspace-hero">
      <div class="workspace-avatar" style="--icon-bg:${escapeHtml(playerIconBg(player))}"><img ${playerIconImgAttrs(player)} alt=""></div>
      <div class="workspace-heading">
        <h2>${heading}</h2>
        ${!special && playerHandle(player) ? `<div class="workspace-handle">#${escapeHtml(playerHandle(player))}</div>` : ''}
        <div class="workspace-meta">${escapeHtml(subtitle)}</div>
        ${canEditPlayer(player.id) && !special ? `<div class="profile-tools">
          <button class="mini-btn" onclick="showPlayerEmojiModal('${player.id}')">Emblema</button>
          <button class="mini-btn" onclick="showPlayerHandleModal('${player.id}')">${playerHandle(player)?'#subnombre':'Definir #subnombre'}</button>
          <button class="mini-btn" onclick="showEditPlayerModal('${player.id}')">Editar perfil</button>
          ${isAdminUser()?`<button class="mini-btn danger" onclick="showDeactivatePlayerModal('${player.id}')">Desactivar</button>`:''}
        </div>`:''}
      </div>
      <div class="workspace-actions">
        ${canAdd ? `<button class="btn btn-primary" type="button" onclick="showAddCharacterModal()">+ Nuevo personaje</button>` : ''}
        ${limitReached && canEditPlayer(player.id) ? `<span class="limit-note">Límite alcanzado: 5/5 activos</span>` : ''}
      </div>
    </div>`;

  grid.replaceChildren();
  if(chars.length===0){
    const empty=document.createElement('p');
    empty.className='empty-note'; empty.style.gridColumn='1 / -1';
    empty.textContent=special ? 'No hay personajes en esta sección.' : 'Este jugador todavía no tiene personajes.';
    grid.appendChild(empty);
    return;
  }

  chars.forEach((c,index)=>{
    const total=totalGold(c.coins);
    const textOnly=isTextOnlyCharacter(c);
    const card=document.createElement('article');
    card.className='card character-card'+(textOnly?' text-only':'');
    card.tabIndex=0; card.setAttribute('role','button');
    card.addEventListener('click',()=>selectCharacter(c.id));
    card.addEventListener('keydown',event=>{ if(event.key==='Enter'||event.key===' '){event.preventDefault();selectCharacter(c.id);} });
    const statusBadge=special ? `<span class="character-status-badge">${escapeHtml(displayPlayerName(player))}</span>` : '';
    const moveAction=characterMoveAction(c,player);
    card.innerHTML=`
      <span class="corner tl"></span><span class="corner tr"></span><span class="corner bl"></span><span class="corner br"></span>
      <div class="character-card-main">
        ${textOnly?'':`<div class="portrait-frame"><img class="portrait-img" ${characterIconImgAttrs(c,index<4)} alt="${escapeHtml(c.name)}"></div>`}
        <div class="character-card-copy">
          ${statusBadge}
          <div class="character-name">${escapeHtml(c.name)}</div>
          <div class="character-wealth">Riqueza equivalente: ${fmtGold(total)} PO</div>
          <div class="mini-coins">${COIN_KEYS.map(k=>`<div class="mini-coin mini-${k}"><strong>${c.coins[k]||0}</strong>${COIN_META[k].abbr}</div>`).join('')}</div>
          ${c.description?`<div class="character-description">${escapeHtml(c.description)}</div>`:''}
        </div>
      </div>
      <div class="character-card-footer">
        <button class="btn btn-primary" type="button" data-open-bank>Abrir banca</button>
        ${canEditCharacter(c)||moveAction ? `<div class="card-actions">
          ${canEditCharacter(c)?`<button class="mini-btn" type="button" data-edit>Editar</button><button class="mini-btn danger" type="button" data-deactivate>Desactivar</button>`:''}
          ${moveAction}
        </div>`:''}
      </div>`;
    card.querySelector('[data-open-bank]')?.addEventListener('click',e=>{e.stopPropagation();selectCharacter(c.id);});
    card.querySelector('[data-edit]')?.addEventListener('click',e=>{e.stopPropagation();showEditCharacterModal(c.id);});
    card.querySelector('[data-deactivate]')?.addEventListener('click',e=>{e.stopPropagation();showDeactivateCharacterModal(c.id);});
    const moveBtn=card.querySelector('[data-move-target]');
    moveBtn?.addEventListener('click',e=>{e.stopPropagation();showMoveSpecialCharacterModal(c.id,moveBtn.dataset.moveTarget);});
    grid.appendChild(card);
  });
}

async function renderBank(){
  showScreen('screen-bank');
  const c = state.characters.find(c=>c.id===state.currentCharacterId);
  const container = document.getElementById('bank-content');
  if(!c){ container.innerHTML = ''; return; }
  container.innerHTML = `<p class="empty-note">Consultando el libro contable...</p>`;
  const movements = await loadMovements(c.id);
  const total = totalGold(c.coins);
  const textOnly = isTextOnlyCharacter(c);

  const pouches = COIN_KEYS.map(k=>`
    <div class="coin-pouch coin-${k}" title="${COIN_META[k].label}">
      ${coinIconSVG(k)}
      <div class="coin-abbr">${COIN_META[k].abbr}</div>
      <div class="coin-count">${c.coins[k]||0}</div>
      <div style="font-size:.72rem;color:var(--ink-soft);margin-top:2px;">${COIN_META[k].label}</div>
    </div>`).join('');

  const newestFirst = movements.slice().reverse();
  const pageSize = 3;
  const totalPages = Math.max(1, Math.ceil(newestFirst.length / pageSize));
  let currentPage = Number(state.historyPage[c.id] || 1);
  currentPage = Math.min(Math.max(1, currentPage), totalPages);
  state.historyPage[c.id] = currentPage;
  const pageItems = newestFirst.slice((currentPage - 1) * pageSize, currentPage * pageSize);

  const historyHtml = pageItems.map(mv=>{
    const deltaParts = COIN_KEYS.filter(k=>mv.delta[k]).map(k=>{
      const v = mv.delta[k];
      const sign = v>0? '+':'';
      return `<span class="coin-tag ${v>0?'positive':'negative'}">${sign}${v} ${COIN_META[k].abbr}</span>`;
    }).join(' ');
    const resultParts = COIN_KEYS.map(k=>`${mv.resultBalance[k]} ${COIN_META[k].abbr}`).join(' · ');
    return `<div class="history-item">
      <div class="history-top">
        <span class="history-type ${mv.type}">${TYPE_LABEL[mv.type]||mv.type}</span>
        <span class="history-date">${dateDisplay(mv.date)}</span>
      </div>
      <div class="history-delta">${deltaParts || '<em>sin cambios</em>'}</div>
      ${mv.reason? `<div class="history-meta">Motivo: ${escapeHtml(mv.reason)}</div>`:''}
      ${mv.author? `<div class="history-meta">Registrado por: ${escapeHtml(mv.author)}</div>`:''}
      <div class="history-result">Saldo resultante: ${resultParts}</div>
    </div>`;
  }).join('') || `<p class="empty-note">Todavía no hay movimientos registrados.</p>`;

  let paginationHtml = '';
  if(newestFirst.length > pageSize){
    const buttons = [];
    const first = Math.max(1, currentPage - 2);
    const last = Math.min(totalPages, first + 4);
    const adjustedFirst = Math.max(1, last - 4);
    for(let page=adjustedFirst; page<=last; page++){
      buttons.push(`<button class="${page===currentPage?'current':''}" onclick="goHistoryPage('${c.id}',${page})">${page}</button>`);
    }
    paginationHtml = `<div class="history-pagination">
      <button onclick="goHistoryPage('${c.id}',${currentPage-1})" ${currentPage===1?'disabled':''}>‹</button>
      ${buttons.join('')}
      <button onclick="goHistoryPage('${c.id}',${currentPage+1})" ${currentPage===totalPages?'disabled':''}>›</button>
      <span class="history-page-label">Página ${currentPage} de ${totalPages}</span>
    </div>`;
  }

  container.innerHTML = `
    <div class="ledger">
      <div class="ledger-page ledger-left">
        <div class="bank-main${textOnly ? ' bank-main-text-only' : ''}">
          ${textOnly ? '' : `<div class="bank-character-art">
            <img ${characterPortraitImgAttrs(c)} alt="${escapeHtml(c.name)}">
          </div>`}
          <div class="bank-details">
            <div class="bank-header">
              <h2 class="bank-name">${escapeHtml(c.name)}</h2>
              <p class="bank-total">Riqueza total: <strong>${fmtGold(total)} PO</strong></p>
            </div>
            ${(c.description || c.notes) ? `<div class="character-lore">
              ${c.description ? `<p>${escapeHtml(c.description)}</p>` : ''}
              ${c.notes ? `<p class="notes"><strong>Notas:</strong> ${escapeHtml(c.notes)}</p>` : ''}
            </div>` : ''}
            <div class="coin-pouch-row">${pouches}</div>
            ${canEditCharacter(c) ? `<div class="action-row">
              <button class="btn btn-primary" onclick="showAddMoneyModal('${c.id}')">Añadir dinero</button>
              <button class="btn btn-primary" onclick="showSpendMoneyModal('${c.id}')">Gastar dinero</button>
              <button class="btn btn-secondary" onclick="showExchangeModal('${c.id}')">Cambiar monedas</button>
              <button class="btn btn-secondary" onclick="showTransferModal('${c.id}')">Transferir</button>
            </div>
            <div class="balance-footnote">Los cambios y transferencias quedan registrados automáticamente.</div>`
            : `<div class="balance-footnote"><strong>Modo lectura.</strong> Inicia sesión para modificar esta banca.</div>`}
          </div>
        </div>
      </div>
      <div class="ledger-page ledger-right">
        <h3 class="ledger-title">Historial de movimientos</h3>
        ${historyHtml}
        ${paginationHtml}
      </div>
    </div>
  `;
}

function goHistoryPage(charId,page){
  const movements = state.movementsCache[charId] || [];
  const totalPages = Math.max(1, Math.ceil(movements.length / 3));
  state.historyPage[charId] = Math.min(Math.max(1, Number(page)||1), totalPages);
  renderBank();
}

/* ================= modal system ================= */
function openModal(html){
  const overlay = document.getElementById('modal-overlay');
  overlay.innerHTML = `<div class="modal-card">${html}</div>`;
  overlay.classList.add('open');
  requestAnimationFrame(()=>{
    const focusTarget = overlay.querySelector('[autofocus], input, select, button');
    if(focusTarget) focusTarget.focus();
  });
}
function closeModal(){
  const overlay = document.getElementById('modal-overlay');
  overlay.classList.remove('open');
  overlay.innerHTML = '';
}
document.getElementById('modal-overlay').addEventListener('mousedown', e=>{
  if(e.target.id==='modal-overlay') closeModal();
});
document.addEventListener('keydown', e=>{
  if(e.key==='Escape' && document.getElementById('modal-overlay').classList.contains('open')) closeModal();
});
function showModalError(msg){
  const el = document.getElementById('modal-error');
  if(el) el.textContent = msg;
}


/* ---- manage players / characters ---- */
function showPlayerEmojiModal(playerId){
  const p = state.players.find(x=>x.id===playerId);
  if(!p) return;

  const currentEmoji = playerEmoji(p);
  const currentBg = /^#[0-9a-f]{6}$/i.test(String(p.iconBg||'')) ? p.iconBg : '#22402F';

  openModal(`
    <h2 class="modal-title">Emblema de ${escapeHtml(p.name)}</h2>
    <p class="modal-detail">Elige el símbolo y el color de fondo del icono.</p>
    <div class="icon-bg-row">
      <div class="icon-preview"><img id="icon-preview-img" src="${playerIconDataUrl(p)}" alt=""></div>
      <label class="text-input" style="margin:0;">Fondo
        <input type="color" id="icon-bg-input" value="${escapeHtml(currentBg)}" style="display:block;margin-top:5px;">
      </label>
    </div>
    <div class="emoji-grid">
      ${FANTASY_EMOJIS.map(item=>`<button type="button" class="emoji-choice ${item.emoji===currentEmoji?'selected':''}" data-emoji="${item.emoji}" title="${escapeHtml(item.label)}" aria-label="${escapeHtml(item.label)}">${item.emoji}</button>`).join('')}
    </div>
    <div class="modal-actions">
      <button class="btn btn-secondary" onclick="closeModal()">Cancelar</button>
      <button class="btn btn-primary" id="save-emblem-btn">Guardar emblema</button>
    </div>
    <div id="modal-error" class="modal-error"></div>
  `);

  let selectedEmoji = currentEmoji;
  let selectedBg = currentBg;

  const redrawPreview = ()=>{
    const temp = {...p, emoji:selectedEmoji, iconBg:selectedBg};
    document.getElementById('icon-preview-img').src = playerIconDataUrl(temp);
  };

  document.querySelectorAll('.emoji-choice').forEach(btn=>{
    btn.onclick = ()=>{
      selectedEmoji = btn.dataset.emoji || currentEmoji;
      document.querySelectorAll('.emoji-choice').forEach(x=>x.classList.toggle('selected',x===btn));
      redrawPreview();
    };
  });

  document.getElementById('icon-bg-input').addEventListener('input',e=>{
    selectedBg = e.target.value;
    redrawPreview();
  });

  document.getElementById('save-emblem-btn').onclick = async ()=>{
    const oldEmoji = p.emoji;
    const oldBg = p.iconBg;
    p.emoji = selectedEmoji;
    p.iconBg = selectedBg;
    const btn = document.getElementById('save-emblem-btn');
    btn.disabled = true;
    btn.textContent = 'Guardando...';

    const ok = await persistPlayer(p);
    if(ok){
      closeModal();
      toast('success','Emblema actualizado.');
      render();
    }else{
      p.emoji = oldEmoji;
      p.iconBg = oldBg;
      btn.disabled = false;
      btn.textContent = 'Guardar emblema';
      showModalError('No se pudo guardar el emblema.');
    }
  };
}

function showPlayerHandleModal(playerId){
  const p = state.players.find(x=>x.id===playerId);
  if(!p) return;
  const current = playerHandle(p);
  openModal(`
    <h2 class="modal-title">#Subnombre de ${escapeHtml(p.name)}</h2>
    <p class="modal-detail">Escribe el nombre corto que aparecerá debajo de tu nombre. El símbolo # se añade automáticamente.</p>
    <label class="text-input">Subnombre
      <div style="display:flex;align-items:center;gap:8px;margin-top:4px;">
        <span style="font-family:'Cinzel',serif;color:var(--gold);font-weight:700;font-size:1.1rem;">#</span>
        <input type="text" id="handle-input" maxlength="30" value="${escapeHtml(current)}" placeholder="ej: dragonrojo" autocomplete="off" autofocus style="margin-top:0;">
      </div>
    </label>
    <p class="modal-detail">Máximo 30 caracteres. Puedes dejarlo vacío si quieres quitarlo.</p>
    <div class="modal-actions">
      <button class="btn btn-secondary" onclick="closeModal()">Cancelar</button>
      <button class="btn btn-primary" id="save-btn">Guardar</button>
    </div>
    <div id="modal-error" class="modal-error"></div>
  `);
  document.getElementById('save-btn').onclick = async ()=>{
    const input = document.getElementById('handle-input').value.trim().replace(/^#+/, '').trim();
    if(input.length > 30){ showModalError('El subnombre puede tener como máximo 30 caracteres.'); return; }
    const oldHandle = p.handle;
    p.handle = input;
    const btn = document.getElementById('save-btn');
    btn.disabled = true; btn.textContent = 'Guardando...';
    const ok = await persistPlayer(p);
    if(ok){
      closeModal();
      toast('success', input ? `Subnombre actualizado: #${input}` : 'Subnombre eliminado.');
      render();
    }else{
      p.handle = oldHandle;
      btn.disabled = false; btn.textContent = 'Guardar';
      showModalError('No se pudo guardar el subnombre.');
    }
  };
}

function showEditPlayerModal(playerId){
  const p = state.players.find(x=>x.id===playerId);
  if(!p) return;
  const adminImageField=isAdminUser()?`<label class="text-input">URL del icono <input type="text" id="player-icon-url" maxlength="300" value="${escapeHtml(p.iconUrl||'')}" placeholder="media/icons/... o https://..."><small>Solo administrador · acepta media/icons/... y lo resuelve dentro de <?= favor_base_path() ?>bancadigital/.</small></label>`:'';
  openModal(`
    <h2 class="modal-title">Editar jugador</h2>
    <label class="text-input">Nombre del jugador<input type="text" id="name-input" maxlength="60" value="${escapeHtml(p.name)}" autofocus></label>
    <label class="text-input">#Subnombre<input type="text" id="handle-input" maxlength="30" value="${escapeHtml(playerHandle(p))}" placeholder="ej: dragonrojo"></label>
    ${adminImageField}
    <div class="modal-actions">
      <button class="btn btn-secondary" onclick="closeModal()">Cancelar</button>
      <button class="btn btn-primary" id="save-btn">Guardar cambios</button>
    </div>
    <div id="modal-error" class="modal-error"></div>
  `);
  document.getElementById('save-btn').onclick = async ()=>{
    const name = document.getElementById('name-input').value.trim();
    if(!name){ showModalError('Escribe un nombre para el jugador.'); return; }
    const handle = document.getElementById('handle-input').value.trim().replace(/^#+/, '').trim();
    const old={name:p.name,handle:p.handle,iconUrl:p.iconUrl||''};
    p.name = name;
    p.handle = handle;
    if(isAdminUser()) p.iconUrl=document.getElementById('player-icon-url').value.trim();
    const btn = document.getElementById('save-btn');
    btn.disabled=true;
    const ok = await persistPlayer(p);
    if(ok){ closeModal(); toast('success','Jugador actualizado.'); render(); }
    else{ p.name=old.name; p.handle=old.handle; p.iconUrl=old.iconUrl; btn.disabled=false; showModalError('No se pudo guardar.'); }
  };
}

function showDeactivatePlayerModal(playerId){
  const p = state.players.find(x=>x.id===playerId);
  if(!p) return;
  const count = state.characters.filter(c=>c.playerId===p.id && c.active!==false).length;
  openModal(`
    <h2 class="modal-title">Desactivar jugador</h2>
    <p class="modal-summary">¿Desactivar a <strong>${escapeHtml(p.name)}</strong>?</p>
    <p class="modal-detail">Sus ${count} personaje${count===1?'':'s'} no se borrarán. Podrás reactivar al jugador después.</p>
    <div class="modal-actions">
      <button class="btn btn-secondary" onclick="closeModal()">Cancelar</button>
      <button class="btn btn-danger" id="confirm-btn">Desactivar</button>
    </div>
    <div id="modal-error" class="modal-error"></div>
  `);
  document.getElementById('confirm-btn').onclick = async ()=>{
    p.active=false;
    const ok=await persistPlayer(p);
    if(ok){
      closeModal(); toast('success','Jugador desactivado.');
      if(state.currentPlayerId===p.id){ goToPlayers(); } else { render(); }
    }
    else{ p.active=true; showModalError('No se pudo guardar.'); }
  };
}

function showEditCharacterModal(charId){
  const c = state.characters.find(x=>x.id===charId);
  if(!c) return;
  const adminImageFields=isAdminUser()?`
    <div class="admin-image-fields">
      <p class="modal-detail"><strong>Imágenes globales · administrador</strong><br>Cambiar estas URLs actualiza la fuente usada por Banca, Inventario y Archivos del Gremio.</p>
      <label class="text-input">URL del icono<input type="text" id="character-icon-url" maxlength="300" value="${escapeHtml(c.iconUrl||'')}" placeholder="media/icons/jugador/personaje.webp"><small>Recomendado: media/icons/jugador/personaje.webp. Se carga desde bancadigital/icons.</small></label>
      <label class="text-input">URL del retrato<input type="text" id="character-portrait-url" maxlength="300" value="${escapeHtml(c.portraitUrl||'')}" placeholder="media/characters/jugador/personaje.webp"><small>Recomendado: media/characters/jugador/personaje.webp. Déjalo vacío para usar la ruta automática de bancadigital/characters.</small></label>
    </div>`:'';
  openModal(`
    <h2 class="modal-title">Editar personaje</h2>
    <label class="text-input">Nombre del personaje<input type="text" id="name-input" maxlength="60" value="${escapeHtml(c.name)}" autofocus></label>
    <label class="text-input">Descripción corta<textarea id="description-input" maxlength="180" placeholder="Quién es, qué hace o cómo lo conocen…">${escapeHtml(c.description||'')}</textarea></label>
    <label class="text-input">Notas personales<textarea id="notes-input" maxlength="600" placeholder="Recordatorios, deudas, objetivos o información útil…">${escapeHtml(c.notes||'')}</textarea></label>
    ${adminImageFields}
    <p class="modal-detail">El dinero se modifica únicamente mediante movimientos para conservar el historial.</p>
    <div class="modal-actions">
      <button class="btn btn-secondary" onclick="closeModal()">Cancelar</button>
      <button class="btn btn-primary" id="save-btn">Guardar cambios</button>
    </div>
    <div id="modal-error" class="modal-error"></div>
  `);
  document.getElementById('save-btn').onclick = async ()=>{
    const name=document.getElementById('name-input').value.trim();
    if(!name){ showModalError('Escribe un nombre para el personaje.'); return; }
    const old={name:c.name,description:c.description,notes:c.notes,iconUrl:c.iconUrl||'',portraitUrl:c.portraitUrl||''};
    c.name=name;
    c.description=document.getElementById('description-input').value.trim();
    c.notes=document.getElementById('notes-input').value.trim();
    if(isAdminUser()){
      c.iconUrl=document.getElementById('character-icon-url').value.trim();
      c.portraitUrl=document.getElementById('character-portrait-url').value.trim();
    }
    const btn=document.getElementById('save-btn'); btn.disabled=true;
    const ok=await persistCharacter(c);
    if(ok){ closeModal(); toast('success','Personaje actualizado.'); render(); }
    else{ c.name=old.name; c.description=old.description; c.notes=old.notes; c.iconUrl=old.iconUrl; c.portraitUrl=old.portraitUrl; btn.disabled=false; showModalError('No se pudo guardar.'); }
  };
}

function showDeactivateCharacterModal(charId){
  const c = state.characters.find(x=>x.id===charId);
  if(!c) return;
  openModal(`
    <h2 class="modal-title">Desactivar personaje</h2>
    <p class="modal-summary">¿Desactivar a <strong>${escapeHtml(c.name)}</strong>?</p>
    <p class="modal-detail">Su dinero y su historial se conservarán y podrás reactivarlo después.</p>
    <div class="modal-actions">
      <button class="btn btn-secondary" onclick="closeModal()">Cancelar</button>
      <button class="btn btn-danger" id="confirm-btn">Desactivar</button>
    </div>
    <div id="modal-error" class="modal-error"></div>
  `);
  document.getElementById('confirm-btn').onclick = async ()=>{
    c.active=false;
    const ok=await persistCharacter(c);
    if(ok){ closeModal(); toast('success','Personaje desactivado.'); render(); }
    else{ c.active=true; showModalError('No se pudo guardar.'); }
  };
}

function showInactiveModal(){
  const inactivePlayers=state.players.filter(p=>p.active===false);
  const inactiveChars=state.characters.filter(c=>c.active===false);
  const playerItems = inactivePlayers.map(p=>`
    <div class="inactive-item"><div><div class="inactive-name">${escapeHtml(p.name)}</div><div class="inactive-meta">Jugador desactivado</div></div>
    <button class="mini-btn" onclick="reactivatePlayer('${p.id}')">Reactivar</button></div>`).join('');
  const charItems = inactiveChars.map(c=>{
    const owner=state.players.find(p=>p.id===c.playerId);
    return `<div class="inactive-item"><div><div class="inactive-name">${escapeHtml(c.name)}</div><div class="inactive-meta">Personaje de ${escapeHtml(owner?owner.name:'Sin jugador')}</div></div>
    <button class="mini-btn" onclick="reactivateCharacter('${c.id}')">Reactivar</button></div>`;
  }).join('');
  openModal(`
    <h2 class="modal-title">Desactivados</h2>
    <h3 class="ledger-title" style="text-align:left;">Jugadores</h3>
    <div class="inactive-list">${playerItems||'<p class="empty-note">No hay jugadores desactivados.</p>'}</div>
    <h3 class="ledger-title" style="text-align:left;margin-top:20px;">Personajes</h3>
    <div class="inactive-list">${charItems||'<p class="empty-note">No hay personajes desactivados.</p>'}</div>
    <div class="modal-actions"><button class="btn btn-secondary" onclick="closeModal()">Cerrar</button></div>
  `);
}

async function reactivatePlayer(playerId){
  const p=state.players.find(x=>x.id===playerId); if(!p) return;
  p.active=true;
  if(await persistPlayer(p)){ toast('success','Jugador reactivado.'); showInactiveModal(); render(); }
  else{ p.active=false; toast('error','No se pudo guardar.'); }
}
async function reactivateCharacter(charId){
  const c=state.characters.find(x=>x.id===charId); if(!c) return;
  c.active=true;
  if(await persistCharacter(c)){ toast('success','Personaje reactivado.'); showInactiveModal(); render(); }
  else{ c.active=false; toast('error','No se pudo guardar.'); }
}

/* ---- mover entre Retirados y Muertos sin cambiar el esquema de BD ---- */
function showMoveSpecialCharacterModal(charId,targetPlayerId){
  const c=state.characters.find(x=>x.id===charId);
  const source=state.players.find(p=>p.id===c?.playerId);
  const target=state.players.find(p=>p.id===targetPlayerId);
  if(!c||!source||!target||!isAdminUser()) return;
  openModal(`
    <h2 class="modal-title">Mover personaje</h2>
    <p class="modal-summary">¿Mover a <strong>${escapeHtml(c.name)}</strong> de <strong>${escapeHtml(displayPlayerName(source))}</strong> a <strong>${escapeHtml(displayPlayerName(target))}</strong>?</p>
    <p class="modal-detail">La banca, el dinero y todo el historial del personaje se conservan.</p>
    <div class="modal-actions"><button class="btn btn-secondary" onclick="closeModal()">Cancelar</button><button class="btn btn-primary" id="move-special-confirm">Mover</button></div>
    <div id="modal-error" class="modal-error"></div>`);
  document.getElementById('move-special-confirm').onclick=async()=>{
    const btn=document.getElementById('move-special-confirm'); btn.disabled=true;
    try{
      const data=await apiRequest('move_character',{character_id:c.id,target_player_id:target.id},'POST');
      if(data.character){ Object.assign(c,data.character); }
      closeModal(); toast('success',`${c.name} ahora está en ${displayPlayerName(target)}.`);
      state.currentPlayerId=target.id; state.currentCharacterId=null; state.screen='characters'; render();
    }catch(e){ btn.disabled=false; showModalError(e.message||'No se pudo mover el personaje.'); }
  };
}

/* ---- add player ---- */
function showAddPlayerModal(){
  openModal(`
    <h2 class="modal-title">Añadir jugador</h2>
    <label class="text-input">Nombre del jugador<input type="text" id="name-input" maxlength="60" autofocus></label>
    <div class="modal-actions">
      <button class="btn btn-secondary" onclick="closeModal()">Cancelar</button>
      <button class="btn btn-primary" id="save-btn">Guardar</button>
    </div>
    <div id="modal-error" class="modal-error"></div>
  `);
  document.getElementById('save-btn').onclick = async ()=>{
    const name = document.getElementById('name-input').value.trim();
    if(!name){ showModalError('Escribe un nombre para el jugador.'); return; }
    const btn = document.getElementById('save-btn');
    btn.disabled = true; btn.textContent = 'Guardando...';
    const player = { id: nextPlayerId(), name, handle:'', order: state.players.length, active: true, emoji: FANTASY_EMOJIS[state.players.length % FANTASY_EMOJIS.length].emoji };
    state.players.push(player);
    const ok = await persistPlayer(player);
    if(ok){ closeModal(); toast('success','Jugador añadido.'); render(); }
    else{ state.players.pop(); btn.disabled=false; btn.textContent='Guardar'; showModalError('No se pudo guardar. Intenta de nuevo.'); }
  };
}

/* ---- add character ---- */
function showAddCharacterModal(){
  const owner=selectedPlayer();
  if(owner && !isSpecialPlayer(owner) && activeCharacterCount(owner.id)>=5){
    toast('error','Este jugador ya tiene el máximo de 5 personajes activos.');
    return;
  }
  openModal(`
    <h2 class="modal-title">Añadir personaje</h2>
    <label class="text-input">Nombre del personaje<input type="text" id="name-input" maxlength="60" autofocus></label>
    <label class="text-input">Descripción corta<textarea id="description-input" maxlength="180" placeholder="Quién es, qué hace o cómo lo conocen…"></textarea></label>
    <label class="text-input">Notas personales<textarea id="notes-input" maxlength="600" placeholder="Recordatorios, deudas, objetivos o información útil…"></textarea></label>
    <p class="modal-detail" style="text-align:left;margin-top:-4px;">Saldo inicial (opcional)</p>
    <div class="coin-inputs">
      ${COIN_KEYS.map(k=>`<label class="coin-input coin-${k}"><span>${COIN_META[k].abbr}</span><input type="number" min="0" step="1" inputmode="numeric" value="0" data-coin="${k}"></label>`).join('')}
    </div>
    <div class="modal-actions">
      <button class="btn btn-secondary" onclick="closeModal()">Cancelar</button>
      <button class="btn btn-primary" id="save-btn">Guardar</button>
    </div>
    <div id="modal-error" class="modal-error"></div>
  `);
  document.getElementById('save-btn').onclick = async ()=>{
    const name = document.getElementById('name-input').value.trim();
    if(!name){ showModalError('Escribe un nombre para el personaje.'); return; }
    const description = document.getElementById('description-input').value.trim();
    const notes = document.getElementById('notes-input').value.trim();
    const coinRead = readCoinInputs();
    if(!coinRead.ok){ showModalError(`${COIN_META[coinRead.coin].label}: ${coinRead.message}`); return; }
    const coins = coinRead.values;
    const btn = document.getElementById('save-btn');
    btn.disabled = true; btn.textContent = 'Guardando...';
    const character = { id: uid(), playerId: state.currentPlayerId, name, description, notes, coins:{ppt:0,po:0,pl:0,pc:0}, active:true };
    state.characters.push(character);
    const ok = await persistCharacter(character, coins);
    if(ok){
      closeModal(); toast('success','Personaje añadido.'); render();
    }else{
      const idx = state.characters.findIndex(x=>x.id===character.id);
      if(idx!==-1) state.characters.splice(idx,1);
      btn.disabled=false; btn.textContent='Guardar';
      showModalError('No se pudo crear el personaje. No se guardó ningún cambio; puedes intentarlo nuevamente.');
    }
  };
}

/* ---- add money ---- */
function showAddMoneyModal(charId){
  const c = state.characters.find(c=>c.id===charId);
  openModal(`
    <h2 class="modal-title">Añadir dinero</h2>
    <p class="modal-detail">${escapeHtml(c.name)}</p>
    <div class="coin-inputs">
      ${COIN_KEYS.map(k=>`<label class="coin-input coin-${k}"><span>${COIN_META[k].abbr}</span><input type="number" min="0" step="1" inputmode="numeric" value="0" data-coin="${k}"></label>`).join('')}
    </div>
    <label class="text-input">Motivo (opcional)<textarea id="reason-input" class="spend-reason" maxlength="255" rows="1" placeholder="Ej: recompensa, venta, devolución…"></textarea></label>
    <p class="modal-detail">El movimiento quedará registrado con tu sesión.</p>
    <label class="checkbox-input"><input type="checkbox" id="correction-input"> Marcar como corrección</label>
    <div class="modal-actions">
      <button class="btn btn-secondary" onclick="closeModal()">Cancelar</button>
      <button class="btn btn-primary" id="continue-btn">Continuar</button>
    </div>
    <div id="modal-error" class="modal-error"></div>
  `);
  const reasonInput = document.getElementById('reason-input');
  reasonInput.addEventListener('input', ()=>{
    reasonInput.style.height='auto';
    reasonInput.style.height=Math.min(reasonInput.scrollHeight, 150)+'px';
  });
  document.getElementById('continue-btn').onclick = ()=>{
    const coinRead = readCoinInputs();
    if(!coinRead.ok){ showModalError(`${COIN_META[coinRead.coin].label}: ${coinRead.message}`); return; }
    const delta = coinRead.values;
    const any = COIN_KEYS.some(k=>delta[k]>0);
    if(!any){ showModalError('Ingresa al menos una cantidad mayor que cero.'); return; }
    const reason = document.getElementById('reason-input').value.trim();
    const author = state.user ? state.user.displayName : ''; 
    const isCorrection = document.getElementById('correction-input').checked;
    showConfirmAddMoney(charId, delta, reason, author, isCorrection);
  };
}
function showConfirmAddMoney(charId, delta, reason, author, isCorrection){
  const c = state.characters.find(c=>c.id===charId);
  const summary = COIN_KEYS.filter(k=>delta[k]>0).map(k=>`${delta[k]} ${COIN_META[k].abbr}`).join(', ');
  openModal(`
    <h2 class="modal-title">Confirmar</h2>
    <p class="modal-summary">Añadirás <strong>${summary}</strong> a ${escapeHtml(c.name)}.</p>
    ${reason?`<p class="modal-detail">Motivo: ${escapeHtml(reason)}</p>`:''}
    ${author?`<p class="modal-detail">Registrado por: ${escapeHtml(author)}</p>`:''}
    <div class="modal-actions">
      <button class="btn btn-secondary" id="back-btn">Atrás</button>
      <button class="btn btn-primary" id="confirm-btn">Confirmar</button>
    </div>
    <div id="modal-error" class="modal-error"></div>
  `);
  document.getElementById('back-btn').onclick = ()=>showAddMoneyModal(charId);
  const confirmBtn = document.getElementById('confirm-btn');
  confirmBtn.onclick = async ()=>{
    confirmBtn.disabled=true; confirmBtn.textContent='Guardando...';
    try{
      await addMovementRecord(c, isCorrection?'correction':'add', delta, reason, author);
      closeModal(); toast('success','Dinero añadido.'); render();
    }catch(e){
      confirmBtn.disabled=false; confirmBtn.textContent='Confirmar';
      showModalError('No se pudo guardar el movimiento. Intenta de nuevo.');
    }
  };
}

/* ---- spend money ---- */
function showSpendMoneyModal(charId){
  const c = state.characters.find(c=>c.id===charId);
  openModal(`
    <h2 class="modal-title">Gastar dinero</h2>
    <p class="modal-detail">${escapeHtml(c.name)}</p>
    <div class="coin-inputs">
      ${COIN_KEYS.map(k=>`<label class="coin-input coin-${k}"><span>${COIN_META[k].abbr} (tienes ${c.coins[k]||0})</span><input type="number" min="0" step="1" inputmode="numeric" value="0" data-coin="${k}"></label>`).join('')}
    </div>
    <label class="text-input">Motivo (opcional)<textarea id="reason-input" class="spend-reason" maxlength="255" rows="1" placeholder="Ej: compra de suministros, posada, equipo…"></textarea></label>
    <p class="modal-detail">El movimiento quedará registrado con tu sesión.</p>
    <label class="checkbox-input"><input type="checkbox" id="correction-input"> Marcar como corrección</label>
    <div class="modal-actions">
      <button class="btn btn-secondary" onclick="closeModal()">Cancelar</button>
      <button class="btn btn-primary" id="continue-btn">Continuar</button>
    </div>
    <div id="modal-error" class="modal-error"></div>
  `);
  const reasonInput = document.getElementById('reason-input');
  reasonInput.addEventListener('input', ()=>{
    reasonInput.style.height='auto';
    reasonInput.style.height=Math.min(reasonInput.scrollHeight, 150)+'px';
  });
  document.getElementById('continue-btn').onclick = ()=>{
    const coinRead = readCoinInputs();
    if(!coinRead.ok){ showModalError(`${COIN_META[coinRead.coin].label}: ${coinRead.message}`); return; }
    const amounts = coinRead.values;
    const any = COIN_KEYS.some(k=>amounts[k]>0);
    if(!any){ showModalError('Ingresa al menos una cantidad mayor que cero.'); return; }
    const spend = spendDeltaWithAutomaticChange(c.coins, amounts);
    if(!spend.ok){
      const missing = spend.requestedTotal - spend.currentTotal;
      showModalError(`No hay fondos suficientes. Te faltan ${missing} PC de valor total.`);
      return;
    }
    const delta = spend.delta;
    const reason = document.getElementById('reason-input').value.trim();
    const author = state.user ? state.user.displayName : ''; 
    const isCorrection = document.getElementById('correction-input').checked;
    showConfirmSpendMoney(charId, delta, reason, author, isCorrection);
  };
}
function showConfirmSpendMoney(charId, delta, reason, author, isCorrection){
  const c = state.characters.find(c=>c.id===charId);
  const spentCopper = -COIN_KEYS.reduce((total,k)=>total + (delta[k]||0) * RATE[k], 0);
  const summary = coinListText(copperToCoins(spentCopper));
  openModal(`
    <h2 class="modal-title">Confirmar</h2>
    <p class="modal-summary">Gastarás <strong>${summary}</strong> de ${escapeHtml(c.name)}.</p>
    ${reason?`<p class="modal-detail">Motivo: ${escapeHtml(reason)}</p>`:''}
    ${author?`<p class="modal-detail">Registrado por: ${escapeHtml(author)}</p>`:''}
    <div class="modal-actions">
      <button class="btn btn-secondary" id="back-btn">Atrás</button>
      <button class="btn btn-primary" id="confirm-btn">Confirmar</button>
    </div>
    <div id="modal-error" class="modal-error"></div>
  `);
  document.getElementById('back-btn').onclick = ()=>showSpendMoneyModal(charId);
  const confirmBtn = document.getElementById('confirm-btn');
  confirmBtn.onclick = async ()=>{
    confirmBtn.disabled=true; confirmBtn.textContent='Guardando...';
    try{
      await addMovementRecord(c, isCorrection?'correction':'spend', delta, reason, author);
      closeModal(); toast('success','Dinero gastado.'); render();
    }catch(e){
      confirmBtn.disabled=false; confirmBtn.textContent='Confirmar';
      showModalError('No se pudo guardar el movimiento. Puede que el saldo haya cambiado; ciérralo e inténtalo de nuevo.');
    }
  };
}


/* ---- transfer between characters ---- */
async function showTransferModal(charId){
  const c = state.characters.find(x=>x.id===charId);
  if(!c) return;

  openModal(`
    <h2 class="modal-title">Transferir dinero</h2>
    <p class="modal-detail">Desde <strong>${escapeHtml(c.name)}</strong></p>
    <p class="transfer-note">Puedes enviar monedas a cualquier personaje activo, incluso de otro jugador.</p>
    <p class="empty-note">Buscando destinatarios...</p>
  `);

  try{
    const data = await apiRequest('transfer_targets',{source_id:charId});
    const targets = data.targets || [];
    if(!targets.length){
      document.querySelector('.modal-card').innerHTML = `
        <h2 class="modal-title">Transferir dinero</h2>
        <p class="empty-note">No hay otros personajes activos disponibles.</p>
        <div class="modal-actions"><button class="btn btn-secondary" onclick="closeModal()">Cerrar</button></div>`;
      return;
    }

    document.querySelector('.modal-card').innerHTML = `
      <h2 class="modal-title">Transferir dinero</h2>
      <p class="modal-detail">Desde <strong>${escapeHtml(c.name)}</strong></p>
      <label class="select-input">Personaje que recibirá
        <select id="transfer-target">
          ${targets.map(t=>`<option value="${escapeHtml(t.id)}">${escapeHtml(t.name)} — ${escapeHtml(t.playerName)}</option>`).join('')}
        </select>
      </label>
      <div class="coin-inputs">
        ${COIN_KEYS.map(k=>`<label class="coin-input coin-${k}"><span>${COIN_META[k].abbr} (tienes ${c.coins[k]||0})</span><input type="number" min="0" step="1" inputmode="numeric" value="0" data-coin="${k}"></label>`).join('')}
      </div>
      <label class="text-input">Motivo (opcional)<input type="text" id="transfer-reason" maxlength="120" placeholder="Pago, préstamo, reparto..."></label>
      <div class="modal-actions">
        <button class="btn btn-secondary" onclick="closeModal()">Cancelar</button>
        <button class="btn btn-primary" id="transfer-continue">Continuar</button>
      </div>
      <div id="modal-error" class="modal-error"></div>
    `;

    document.getElementById('transfer-continue').onclick = ()=>{
      const coinRead = readCoinInputs();
      if(!coinRead.ok){ showModalError(`${COIN_META[coinRead.coin].label}: ${coinRead.message}`); return; }
      const amounts = coinRead.values;
      if(!COIN_KEYS.some(k=>amounts[k]>0)){ showModalError('Ingresa al menos una cantidad mayor que cero.'); return; }
      for(const k of COIN_KEYS){
        if(amounts[k] > (c.coins[k]||0)){
          showModalError(`No hay suficiente ${COIN_META[k].label}. Disponible: ${c.coins[k]||0}.`);
          return;
        }
      }
      const targetId = document.getElementById('transfer-target').value;
      const target = targets.find(t=>t.id===targetId);
      const reason = document.getElementById('transfer-reason').value.trim();
      showConfirmTransfer(charId,target,amounts,reason);
    };
  }catch(e){
    document.querySelector('.modal-card').innerHTML = `
      <h2 class="modal-title">Transferir dinero</h2>
      <p class="modal-error">${escapeHtml(e.message)}</p>
      <div class="modal-actions"><button class="btn btn-secondary" onclick="closeModal()">Cerrar</button></div>`;
  }
}

function showConfirmTransfer(sourceId,target,amounts,reason){
  const source = state.characters.find(x=>x.id===sourceId);
  if(!source || !target) return;
  const summary = COIN_KEYS.filter(k=>amounts[k]>0).map(k=>`${amounts[k]} ${COIN_META[k].abbr}`).join(', ');

  openModal(`
    <h2 class="modal-title">Confirmar transferencia</h2>
    <div class="transfer-confirm-card">
      <div><small>SALE DE</small><strong>${escapeHtml(source.name)}</strong></div>
      <span class="transfer-confirm-arrow">→</span>
      <div><small>RECIBE</small><strong>${escapeHtml(target.name)}</strong><em>${escapeHtml(target.playerName)}</em></div>
    </div>
    <div class="transfer-confirm-amount">${summary}</div>
    ${reason?`<p class="modal-detail">Motivo: ${escapeHtml(reason)}</p>`:''}
    <label class="transfer-confirm-check"><input type="checkbox" id="transfer-confirm-check"> <span>Confirmo que revisé el destinatario y las cantidades.</span></label>
    <div class="modal-actions">
      <button class="btn btn-secondary" onclick="showTransferModal('${sourceId}')">Atrás</button>
      <button class="btn btn-primary" id="confirm-transfer-btn" disabled>Confirmar y transferir</button>
    </div>
    <div id="modal-error" class="modal-error"></div>
  `);

  const confirmCheck=document.getElementById('transfer-confirm-check');
  const confirmTransferButton=document.getElementById('confirm-transfer-btn');
  confirmCheck.addEventListener('change',()=>{confirmTransferButton.disabled=!confirmCheck.checked;});
  confirmTransferButton.onclick = async ()=>{
    const btn = document.getElementById('confirm-transfer-btn');
    btn.disabled = true;
    btn.textContent = 'Transfiriendo...';
    try{
      const data = await apiRequest('transfer',{
        source_id:sourceId,
        target_id:target.id,
        amounts,
        reason
      },'POST');

      state.movementsCache[sourceId] = data.movements || [];
      state.historyPage[sourceId] = 1;
      if(data.source){
        const sourceIndex=state.characters.findIndex(item=>item.id===data.source.id);
        if(sourceIndex!==-1) Object.assign(state.characters[sourceIndex],data.source);
      }
      if(data.target){
        const targetIndex=state.characters.findIndex(item=>item.id===data.target.id);
        if(targetIndex!==-1) Object.assign(state.characters[targetIndex],data.target);
      }

      closeModal();
      toast('success',`Transferencia enviada a ${target.name}.`);
      render();
    }catch(e){
      btn.disabled = false;
      btn.textContent = 'Transferir';
      showModalError(e.message || 'No se pudo completar la transferencia.');
    }
  };
}


/* ---- exchange coins ---- */
function showExchangeModal(charId){
  const c = state.characters.find(c=>c.id===charId);
  const options = k=>`<option value="${k}">${COIN_META[k].label} (${COIN_META[k].abbr})</option>`;
  openModal(`
    <h2 class="modal-title">Cambiar monedas</h2>
    <p class="modal-detail">${escapeHtml(c.name)}</p>
    <label class="select-input">Moneda que entregarás
      <select id="give-coin">${COIN_KEYS.map(options).join('')}</select>
    </label>
    <label class="text-input">Cantidad a entregar<input type="number" min="1" step="1" inputmode="numeric" id="give-amount" value="1"></label>
    <label class="select-input">Moneda que recibirás
      <select id="receive-coin">${COIN_KEYS.map(options).join('')}</select>
    </label>
    <div id="exchange-preview" class="operation-preview">Elige las monedas y una cantidad para ver el cambio.</div>
    <p class="modal-detail">El movimiento quedará registrado con tu sesión.</p>
    <div class="modal-actions">
      <button class="btn btn-secondary" onclick="closeModal()">Cancelar</button>
      <button class="btn btn-primary" id="continue-btn">Continuar</button>
    </div>
    <div id="modal-error" class="modal-error"></div>
  `);
  document.getElementById('receive-coin').value = COIN_KEYS[1];
  const updateExchangePreview = ()=>{
    const giveType = document.getElementById('give-coin').value;
    const receiveType = document.getElementById('receive-coin').value;
    const amountRead = readWholeNumberInput(document.getElementById('give-amount'), {min:1, emptyAsZero:false});
    const preview = document.getElementById('exchange-preview');
    if(!amountRead.ok){ preview.textContent = 'Ingresa una cantidad entera válida.'; return; }
    if(giveType===receiveType){ preview.textContent = 'Selecciona dos monedas diferentes.'; return; }
    const receiveAmt = exchangeCalc(giveType, amountRead.value, receiveType);
    if(receiveAmt===null){ preview.textContent = 'La conversión no es exacta; cambia la cantidad.'; return; }
    preview.innerHTML = `Entregarás <strong>${amountRead.value} ${COIN_META[giveType].abbr}</strong> y recibirás <strong>${receiveAmt} ${COIN_META[receiveType].abbr}</strong>.`;
  };
  ['give-coin','give-amount','receive-coin'].forEach(id=>{
    document.getElementById(id).addEventListener('input', updateExchangePreview);
    document.getElementById(id).addEventListener('change', updateExchangePreview);
  });
  updateExchangePreview();

  document.getElementById('continue-btn').onclick = ()=>{
    const giveType = document.getElementById('give-coin').value;
    const receiveType = document.getElementById('receive-coin').value;
    const amountRead = readWholeNumberInput(document.getElementById('give-amount'), {min:1, emptyAsZero:false});
    if(!amountRead.ok){ showModalError(amountRead.message); return; }
    const giveAmt = amountRead.value;
    if(giveType===receiveType){ showModalError('Elige dos monedas diferentes.'); return; }
    if(giveAmt > (c.coins[giveType]||0)){ showModalError(`No hay suficiente ${COIN_META[giveType].label} (${COIN_META[giveType].abbr}). Disponible: ${c.coins[giveType]||0}.`); return; }
    const receiveAmt = exchangeCalc(giveType, giveAmt, receiveType);
    if(receiveAmt===null){ showModalError('Esa cantidad no permite una conversión exacta. Prueba con otra cantidad.'); return; }
    const author = state.user ? state.user.displayName : ''; 
    showConfirmExchange(charId, giveType, giveAmt, receiveType, receiveAmt, author);
  };
}
function showConfirmExchange(charId, giveType, giveAmt, receiveType, receiveAmt, author){
  const c = state.characters.find(c=>c.id===charId);
  openModal(`
    <h2 class="modal-title">Confirmar cambio</h2>
    <p class="modal-summary">Entregarás <strong>${giveAmt} ${COIN_META[giveType].abbr}</strong> y recibirás <strong>${receiveAmt} ${COIN_META[receiveType].abbr}</strong>.</p>
    ${author?`<p class="modal-detail">Registrado por: ${escapeHtml(author)}</p>`:''}
    <div class="modal-actions">
      <button class="btn btn-secondary" id="back-btn">Atrás</button>
      <button class="btn btn-primary" id="confirm-btn">Confirmar</button>
    </div>
    <div id="modal-error" class="modal-error"></div>
  `);
  document.getElementById('back-btn').onclick = ()=>showExchangeModal(charId);
  const confirmBtn = document.getElementById('confirm-btn');
  confirmBtn.onclick = async ()=>{
    confirmBtn.disabled=true; confirmBtn.textContent='Guardando...';
    try{
      const delta = {};
      delta[giveType] = -giveAmt;
      delta[receiveType] = (delta[receiveType]||0) + receiveAmt;
      await addMovementRecord(c, 'exchange', delta, '', author);
      closeModal(); toast('success','Monedas cambiadas.'); render();
    }catch(e){
      confirmBtn.disabled=false; confirmBtn.textContent='Confirmar';
      showModalError('No se pudo guardar el movimiento. Puede que el saldo haya cambiado; ciérralo e inténtalo de nuevo.');
    }
  };
}
</script>
</body>
</html>
