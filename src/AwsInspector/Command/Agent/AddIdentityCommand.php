<?php

namespace AwsInspector\Command\Agent;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class AddIdentityCommand extends Command
{

    protected function configure()
    {
        $this
            ->setName('agent:add-identity')
            ->setDescription('Add identity')
            ->addArgument(
                'key',
                InputArgument::REQUIRED,
                'private key'
            );
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $key = $input->getArgument('key');
        $identity = new \AwsInspector\Ssh\Identity(\AwsInspector\Ssh\PrivateKey::get($key), true);
        $identity->loadIdentity();
    }

}