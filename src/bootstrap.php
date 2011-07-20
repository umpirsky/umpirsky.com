<?php

/**
 * This file is part of the umpirsky.com website.
 * 
 * (c) Saša Stamenković <umpirsky@gmail.com>
 */

include __DIR__ . '/../vendor/Silex/autoload.php'; 

use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

$app = new Silex\Application(); 

// Register extensions
$app->register(new Silex\Extension\TwigExtension(), array(
    'twig.path' => __DIR__ . '/../views',
    'twig.class_path' => __DIR__ . '/../vendor/Twig/lib',
));

$app->register(new Silex\Extension\UrlGeneratorExtension());

$app->register(new Silex\Extension\FormExtension(), array(
    'form.class_path' => __DIR__ . '/../vendor/symfony/src'
));

$app->register(new Silex\Extension\TranslationExtension(), array(
  'locale_fallback' => 'en',
  'translation.class_path' => __DIR__ . '/../vendor/symfony/src',
  'translator.messages' => array()
));

$app->register(new Silex\Extension\SymfonyBridgesExtension(), array(
    'symfony_bridges.class_path' => __DIR__ . '/../vendor/symfony/src'
));

// Add layout
$app->before(function () use ($app) {
    $app['twig']->addGlobal('layout', $app['twig']->loadTemplate('layout.twig'));
});


// Register error handlers
$app->error(
    function (\Exception $e) use ($app) {
        if ($e instanceof NotFoundHttpException) {
            return $app['twig']->render('error.twig', array('code' => 404));
        }

        $code = ($e instanceof HttpException) ? $e->getStatusCode() : 500;
        return $app['twig']->render('error.twig', array('code' => $code));
    }
);

// Add pages
$pages = array(
    '/' => 'about',
    '/portfolio' => 'portfolio',
    '/social' => 'social'
);

foreach ($pages as $route => $view) {
    $app->get($route, function () use ($app, $view) {
        return $app['twig']->render($view . '.twig');
    })->bind($view);
}

$app->match('/contact', function () use ($app, $view) {

    $form = $app['form.factory']
	    ->createBuilder('form')
	    ->add('name', 'text', array('label' => 'Name:'))
	    ->add('email', 'email', array('label' => 'Email:'))
	    ->add('message', 'textarea', array('label' => 'Message:'))
	    ->getForm();

    if ('POST' == $app['request']->getMethod()) {
        $form->bindRequest($app['request']);
        if ($form->isValid()) {
            $data = $form->getData();

            require_once __DIR__ . '/../vendor/swiftmailer/lib/swift_required.php';
            $message = \Swift_Message::newInstance()
                ->setSubject(sprintf('Contact from %s', $_SERVER['SERVER_NAME']))
                ->setFrom(array($data['email']))
                ->setTo(array('umpirsky@gmail.com'))
                ->setBody($data['message']);

            $transport = \Swift_MailTransport::newInstance();
            $mailer = \Swift_Mailer::newInstance($transport);
            $mailer->send($message);

            return $app->redirect($app['url_generator']->generate('contact'));
        }
    }

    return $app['twig']->render('contact.twig', array('form' => $form->createView()));
})->bind('contact');

// Run
$app->run();
