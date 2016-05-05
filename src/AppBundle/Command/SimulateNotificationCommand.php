<?php

namespace AppBundle\Command;

use GuzzleHttp\Exception\RequestException;
use Symfony\Bundle\FrameworkBundle\Command\ContainerAwareCommand;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

class SimulateNotificationCommand extends ContainerAwareCommand
{
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
        $this->output = $output;
        $endpoint = $input->getArgument('endpoint');

        try {
            $this->getContainer()->get('request_simulator')->simulate($endpoint);
        } catch (RequestException $e) {
            if ($e->hasResponse()) {
                $this->output->writeln('INVALID RESPONSE CODE: ' . $e->getResponse()->getStatusCode());
            }
        }
        $this->output->writeln('Sent!');
    }
}
