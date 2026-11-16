<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Gemini\Laravel\Facades\Gemini;

class TestGemini extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'gemini:test';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Test the Google Gemini API connection';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $apiKey = config('gemini.api_key');
        
        if (empty($apiKey)) {
            $this->error('GEMINI_API_KEY is not set in your .env file!');
            return Command::FAILURE;
        }

        $this->info('Testing Google Gemini connection...');

        try {
            $response = Gemini::generativeModel(config('gemini.model'))
                ->generateContent("Hello Gemini, write 'Hello World!'");
            
            $this->newLine();
            $this->info('--- Response ---');
            $this->line(trim($response->text()));
            $this->info('----------------');
            $this->newLine();
            $this->info('Success! The Gemini connection is working properly.');
            return Command::SUCCESS;
        } catch (\Exception $e) {
            $this->error('Failed to communicate with Gemini API!');
            $this->error($e->getMessage());
            return Command::FAILURE;
        }
    }
}
