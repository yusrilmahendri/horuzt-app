<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB; // Importing DB facade
use League\Csv\Reader; // Import the Reader class from League CSV

class BankSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
          // Path to the CSV file
          $csvFilePath = public_path('assets-bank/bank.csv'); // Use public_path() for files in the public directory

          // Use the League\Csv library to read the CSV file
          $csv = Reader::createFromPath($csvFilePath, 'r');
          $csv->setHeaderOffset(0); 
  
          // Loop through the rows and insert them into the database
          foreach ($csv as $row) {
              DB::table('banks')->insert([
                  'kode_bank' => $row['code'], // Make sure to match the header in your CSV
                  'name' => $row['name'],
              ]);
          }
    }
}
