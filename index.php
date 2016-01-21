<?php
if (!empty($_POST['rules'])) {
    error_reporting(E_ALL & ~E_NOTICE);

    require_once 'rew.phps';

    $RC = new rewriteConf($_POST['rules']);
    $RC->parseContent();
    $RC->writeConfig();
    exit($RC->confOk);
}
?><!doctype html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <title>Rule convertor, convert apache htaccess rewrite rules to nginx rewrite rules automatically</title>
    <link href='https://fonts.googleapis.com/css?family=Roboto:400,100' rel='stylesheet' type='text/css'>
    <link rel="stylesheet" href="style.css">
</head>
<body>
<header>
    <h1>Apache2Nginx rules converter</h1>
    <i>attention: not so much beta, but check twice before using!</i>
</header>
<section>
    <article id="apache">
        <h2>Apache Rewrite Rules</h2>
        <textarea placeholder="Paste your rules here"></textarea>
    </article>
    <div id="loader">
        <div class="loader-inner pacman">
            <div></div>
            <div></div>
            <div></div>
            <div></div>
            <div></div>
        </div>
    </div>
    <article id="nginx">
        <h2>Nginx converted Rules</h2>
        <textarea placeholder="The magic will appears there"></textarea>
    </article>
</section>
<aside>
    <p>
        <strong>Will you make the codes public?</strong>
        Please check <a href="https://github.com/mow/apache2nginx">https://github.com/mow/apache2nginx</a> also you can contact from: anil(at)saog.net
    </p>
    <p>
        <strong>Donations?</strong>
        Yes I accept donations. Please use my paypal account: pp(at)saog.net
    </p>
    <p>
        <strong>Does it supports xxx?</strong>
        This page has woozy codes, I dont know try it yourself and see. Backreferances, most of the variables and a
        few flags are supported.There are tons of fancy hacks for catching rewrite behavior of apache. As you see, it
        highly uses variables for deciding to rewriting and there must be many much errors/unmatched directives.
    </p>
</aside>
<footer>
    A converter by <a href="http://anilcetin.com">Mow</a> - UI by <a href="http://ownweb.fr">OwnWeb</a>
</footer>
<script src="https://code.jquery.com/jquery-2.2.0.min.js"></script>
<script src="app.js"></script>
</body>
</html>
