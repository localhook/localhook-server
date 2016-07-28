<?php

namespace AppBundle\Command;

use GuzzleHttp\Exception\RequestException;
use Symfony\Bundle\FrameworkBundle\Command\ContainerAwareCommand;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class SimulateNotificationCommand extends ContainerAwareCommand
{
    /** @var OutputInterface */
    private $output;

    protected function configure()
    {
        $this
            ->setName('app:simulate-notification')
            ->addArgument('user', InputArgument::REQUIRED, 'The endpoint')
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
        try {
            $this->getContainer()->get('request_simulator')->simulate($input->getArgument('user'), $input->getArgument('endpoint'));
        } catch (RequestException $e) {
            if ($e->hasResponse()) {
                $output->writeln('INVALID RESPONSE CODE: ' . $e->getResponse()->getStatusCode());
            }
        }
        $output->writeln('Sent!');
    }
}
