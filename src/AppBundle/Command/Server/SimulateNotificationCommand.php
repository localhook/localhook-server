<?php

namespace AppBundle\Command\Server;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\RequestException;
use GuzzleHttp\Psr7\Request as GuzzleRequest;
use Symfony\Bundle\FrameworkBundle\Command\ContainerAwareCommand;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\HttpFoundation\Request;

class SimulateNotificationCommand extends ContainerAwareCommand
{
    /** @var SymfonyStyle */
    private $io;

    /** @var OutputInterface */
    private $output;

    protected function configure()
    {
        $this
            ->setName('app:server:simulate-notification')
            ->addArgument('endpoint', InputArgument::REQUIRED, 'The endpoint')
            ->addOption('mode', null, InputOption::VALUE_OPTIONAL, 'The mode to send notification : HTTP / INTERNAL', 'HTTP')
            ->setDescription('Simulate a notification to the socket IO server');
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
        $mode = $input->getOption('mode');
        $endpoint = $input->getArgument('endpoint');
        if ($mode == 'HTTP') {
            $this->httpExecution($endpoint);
        } elseif ($mode == 'INTERNAL') {
            $this->internalExecution($endpoint);
        } else {
            throw new \Exception('Invalid mode: please set one of HTTP or INTERNAL values.');
        }
        $this->output->writeln('Sent!');
    }

    private function httpExecution($endpoint)
    {
        $url = 'http://localhost:8000/' . $endpoint . '/notifications?' . http_build_query([
                'post_param_1' => 'post_value_1',
                'post_param_2' => 'post_value_2',
            ]);
        $client = new Client();
        $request = new GuzzleRequest('POST', $url, [
            'json' => [
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

    private function internalExecution($endpoint)
    {
        $socketIoConnector = $this->getContainer()->get('socket_io_connector')->ensureConnection();
        $em = $this->getContainer()->get('doctrine.orm.default_entity_manager');
        $webHook = $em->getRepository('AppBundle:WebHook')->findOneBy(['endpoint' => $endpoint]);
        $request = new Request();
        $request->query->add(['param_1' => 'value_1', 'param_2' => 'value_2']);
        $request->request->add(['param_1' => 'value_1', 'param_2' => 'value_2']);
        $request->setMethod('POST');
        $socketIoConnector->forwardNotification($webHook, $request);
    }
}