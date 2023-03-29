<?php
declare(strict_types=1);

namespace CalApi;

use CalApi\CalApiClient;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Helper\Table;

class CalApiCLI
{
    private CalApiClient $client;

    public function __construct(CalApiClient $client)
    {
        $this->client = $client;
    }

    public function usersList(SymfonyStyle $io): void
    {
        $response = $this->client->makeAdminRequest('GET', 'users');
        $io->title('List of users');
        if ($response === null) {
            $io->writeln('Empty list');
            return;
        }
        $io->listing($response);    
    }

    public function usersAdd(SymfonyStyle $io, $name): void
    {
        $user = ['name' => $name];
        $response = $this->client->makeAdminRequest('POST', 'users', $user);
        $io->success("User [$name] added");
    }

    public function usersDelete(SymfonyStyle $io, $name): void
    {
        $response = $this->client->makeAdminRequest('DELETE',
                                                    'users/' . $name);
        $io->success("User [$name] deleted");
    }

    public function eventsList(SymfonyStyle $io): void
    {
        $response = $this->client->makeAdminRequest('GET', 'events');
        $io->title('List of events');
        if ($response === null) {
            $io->writeln('Empty list');
            return;
        }

        $table = new Table($io);
        $table
            ->setHeaders(['id', 'title', 'start', 'end'])
            ->setRows($response);
        $table->render();
    }


    public function debugOutput(OutputInterface $io)
    {
        if ($_ENV['SHELL_VERBOSITY'] > 1) {
            $io->writeln('');
            $io->writeln('PSR7 MESSAGES');
            $io->writeln('=============');
            $io->writeln('');
            foreach ($this->client->getTransactionsDebug() as $transaction) {
                foreach ($transaction['request'] as $request_line) {
                    $io->writeln('> ' . $request_line);
                }
                $io->writeln('');
                if ($transaction['response']) {
                    foreach ($transaction['response'] as $response_line) {
                        $io->writeln('< ' . $response_line);
                    }
                    $io->writeln('');
                }
            }
        }   

        if ($_ENV['SHELL_VERBOSITY'] > 2) {
            rewind($this->client->debug_buffer);
            $io->writeln('CURLOPT_VERBOSE');
            $io->writeln('===============');
            $io->writeln('');
            $io->writeln(stream_get_contents($this->client->debug_buffer));
        }
    }

}