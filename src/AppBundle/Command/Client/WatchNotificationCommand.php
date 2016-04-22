<?php

namespace AppBundle\Command\Client;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\RequestException;
use GuzzleHttp\Psr7\Request;
use Symfony\Bundle\FrameworkBundle\Command\ContainerAwareCommand;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class WatchNotificationCommand extends ContainerAwareCommand
{
    protected function configure()
    {
        $this
            ->setName('app:client:watch-notifications')
            ->addArgument('endpoint', InputArgument::REQUIRED, 'The name of the endpoint.')
            ->addOption('max', null, InputOption::VALUE_OPTIONAL, 'The maximum number of notification before stop watcher', null)
            ->setDescription('Watch for a notification and output it in JSON format.');
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $endpoint = $input->getArgument('endpoint');
        $webHook = $this->getContainer()
                        ->get('doctrine.orm.default_entity_manager')
                        ->getRepository('AppBundle:WebHook')
                        ->findOneBy(['endpoint' => $endpoint]);
        $privateKey = $webHook->getPrivateKey();
        $max = $input->getOption('max');
        $counter = 0;

        $output->writeln('Watch for a notification to endpoint ' . $endpoint . ' ...');
        $socketIoClientConnector = $this->getContainer()->get('socket_io_client_connector')->ensureConnection();

        $socketIoClientConnector->subscribeChannel($endpoint, $privateKey);

        while (true) {
            // apply max limitation
            if (!is_null($max) && $counter >= $max) {
                break;
            }
            $counter++;

            $notification = $socketIoClientConnector->waitForNotification();
            $url = 'http://localhost/notifications';

            $client = new Client();
            $request = new Request($notification['method'], $url);

            $output->writeln('REQUEST: ' . $notification['method'] . ' ' . $url);

            try {
                $response = $client->send($request, ['timeout' => 15]);
                $output->writeln('RESPONSE: ' . $response->getStatusCode());
            } catch (RequestException $e) {
                if ($e->hasResponse()) {
                    $output->writeln('RESPONSE: ' . $e->getResponse()->getStatusCode());
                }
            }
        }

        $socketIoClientConnector->closeConnection();
    }
}