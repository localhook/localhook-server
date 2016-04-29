<?php

namespace AppBundle\Command;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\RequestException;
use GuzzleHttp\Psr7\Request as GuzzleRequest;
use Symfony\Bundle\FrameworkBundle\Command\ContainerAwareCommand;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

class SimulateNotificationCommand extends ContainerAwareCommand
{
    /** @var SymfonyStyle */
    private $io;

    /** @var OutputInterface */
    private $output;

    protected function configure()
    {
        $this
            ->setName('app:simulate-notification')
            ->addArgument('endpoint', InputArgument::REQUIRED, 'The endpoint')
            ->setDescription('Simulate a notification');
    }

    /**
     * @param InputInterface  $input
     * @param OutputInterface $output
     *
     * @return int|null|void
     * @throws \Exception
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $this->io = new SymfonyStyle($input, $output);
        $this->output = $output;
        $endpoint = $input->getArgument('endpoint');
        $this->httpExecution($endpoint);
        $this->output->writeln('Sent!');
    }

    private function httpExecution($endpoint)
    {
        $notificationPrefixUrl = $this->getContainer()->getParameter('notifications_prefix_url');

        $url = $notificationPrefixUrl . '/' . $endpoint . '/notifications?' . http_build_query([
                'get_param_1' => 'get_value_1',
                'get_param_2' => 'get_value_2',
            ]);
        $this->io->comment('URL: '.$url);
        $client = new Client();
        $request = new GuzzleRequest('POST', $url, [
            'params' => [
                'post_param_1' => 'post_value_1',
                'post_param_2' => 'post_value_2',
            ],
        ]);
        try {
            $client->send($request, ['timeout' => 15]);
        } catch (RequestException $e) {
            if ($e->hasResponse()) {
                $this->output->writeln('INVALID RESPONSE CODE: ' . $e->getResponse()->getStatusCode());
            }
        }
    }
}
