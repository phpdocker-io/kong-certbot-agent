#!/usr/bin/php
<?php

use PhpDockerIo\KongCertbot\Command\UpdateCertificatesCommand;
use Symfony\Component\Console\Application;

include __DIR__ . '/vendor/autoload.php';

$app = new Application('Kong certbot agent');
$app->add(new UpdateCertificatesCommand());
$app->run();
