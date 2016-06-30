<?php

namespace StackFormation\Profile;

use StackFormation\StackFactory;
use Symfony\Component\Console\Output\OutputInterface;

class Manager {

    protected $sdk;
    protected $clients = [];
    protected $stackFactories = [];
    protected $credentialProvider;
    protected $output;

    public function __construct(YamlCredentialProvider $credentialProvider=null, OutputInterface $output=null)
    {
        $this->credentialProvider = is_null($credentialProvider) ? new YamlCredentialProvider() : $credentialProvider;
        $this->output = $output;
    }

    protected function getSdk()
    {
        if (is_null($this->sdk)) {
            $region = getenv('AWS_DEFAULT_REGION');
            if (empty($region)) {
                throw new \Exception('Environment variable AWS_DEFAULT_REGION not set.');
            }
            $this->sdk = new \Aws\Sdk([
                'version' => 'latest',
                'region' => $region,
                'retries' => 20
            ]);
        }
        return $this->sdk;
    }

    /**
     * @param string $client
     * @param string $profile
     * @param array $args
     * @return \Aws\AwsClientInterface
     * @throws \Exception
     */
    public function getClient($client, $profile=null, array $args=[]) {
        if (!is_string($client)) {
            throw new \InvalidArgumentException('Client parameter must be a string');
        }
        if (!is_null($profile) && !is_string($profile)) {
            throw new \InvalidArgumentException('Profile parameter must be a string');
        }
        $cacheKey = $client .'-'. ($profile ? $profile : '__empty__');
        if (!isset($this->clients[$cacheKey])) {
            if ($profile) {
                $args['credentials'] = $this->credentialProvider->getCredentialsForProfile($profile);
            }
            $this->printDebug($client, $profile);
            $this->clients[$cacheKey] = $this->getSdk()->createClient($client, $args);
        }
        return $this->clients[$cacheKey];
    }

    protected function printDebug($client, $profile) {
        if (!$this->output || !$this->output->isVerbose()) {
            return;
        }
        $message = "[ProfileManager] Created '$client' client";
        if ($profile) {
            $message .= " for profile '$profile'";
        } elseif ($profileFromEnv = getenv('AWSINSPECTOR_PROFILE')) {
            $message .= " for profile '$profileFromEnv' with default credentials provider (env/ini/instance)";
        } else {
            $message .= " with default credentials provider (env/ini/instance)";
        }
        $this->output->writeln($message);
    }

    /**
     * @return \Aws\CloudFormation\CloudFormationClient
     */
    public function getCfnClient($profile=null, array $args=[]) {
        return $this->getClient('CloudFormation', $profile, $args);
    }

    public function listAllProfiles()
    {
        return $this->credentialProvider->listAllProfiles();
    }

    public function getEnvVarsFromProfile($profile) {
        $tmp = [];
        foreach ($this->credentialProvider->getEnvVarsForProfile($profile) as $var => $value) {
            $tmp[] = "$var=$value";
        }
        return $tmp;
    }

    public function writeProfileToDotEnv($profile, $file='.env') {
        $tmp = $this->getEnvVarsFromProfile($profile);
        $res = file_put_contents($file, implode("\n", $tmp));
        if ($res === false) {
            throw new \Exception('Error while writing file .env');
        }
        return $file;
    }

    /**
     * "StackFactory" Factory :)
     *
     * @param $profile
     * @return StackFactory
     */
    public function getStackFactory($profile=null) {
        $cachKey = ($profile ? $profile : '__empty__');
        if (!isset($this->stackFactories[$cachKey])) {
            $this->stackFactories[$cachKey] = new StackFactory($this->getCfnClient($profile));
        }
        return $this->stackFactories[$cachKey];
    }

}