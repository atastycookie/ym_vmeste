<?php
namespace Vmeste\SaasBundle\Command;

use Symfony\Bundle\FrameworkBundle\Command\ContainerAwareCommand;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Vmeste\SaasBundle\Util\Rebilling;

/**
 * To launch use: $ app/console vmeste:recurrent
 * Class RecurrentCommand
 * @package Vmeste\SaasBundle\Command
 */
class RecurrentCommand extends ContainerAwareCommand
{
    const ACTIVE = 1;

    const NON_ACTIVE = 0;

    protected function configure()
    {
        $this
            ->setName('vmeste:recurrent')
            ->setDescription('Notify and run recurrents');
        /*->addArgument('name', InputArgument::OPTIONAL, 'Who do you want to greet?')
        ->addOption('yell', null, InputOption::VALUE_NONE, 'If set, the task will yell in uppercase letters');*/
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $doctrine = $this->getContainer()->get('doctrine');
        $em = $doctrine->getManager();

        $params = array(
            'ymurl' =>  $this->getContainer()->getParameter('recurrent.ymurl'),
            'path_to_cert' =>  $this->getContainer()->getParameter('recurrent.path_to_cert'),
            'path_to_key' =>  $this->getContainer()->getParameter('recurrent.path_to_key'),
            'cert_pass' =>  $this->getContainer()->getParameter('recurrent.cert_pass'),
            'icpdo' => $em,
            'apphost' =>  $this->getContainer()->getParameter('recurrent.apphost')
        );
        $recurrent = new Rebilling($params);
        $recurrent->notify();
        $recurrent->run();
        echo("Finished" . "\n\n");
    }
} 