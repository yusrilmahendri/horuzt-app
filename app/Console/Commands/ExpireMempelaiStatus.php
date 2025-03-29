<?php

namespace App\Console\Commands;
use Illuminate\Console\Command;
use App\Models\Mempelai;
use Carbon\Carbon;


class ExpireMempelaiStatus extends Command
{
    // /**
    //  * The name and signature of the console command.
    //  *
    //  * @var string
    //  */
    // protected $signature = 'app:expire-mempelai-status';

    // /**
    //  * The console command description.
    //  *
    //  * @var string
    //  */
    // protected $description = 'Command description';

    // /**
    //  * Execute the console command.
    //  */
    // public function handle()
    // {
    //     //
    // }


        protected $signature = 'mempelai:expire';
        protected $description = 'Update status mempelai menjadi Expired jika lebih dari 24 jam';

        public function handle()
        {
            $expiredTime = Carbon::now()->subHours(24); // Ambil waktu sekarang dikurangi 24 jam

            $mempelais = Mempelai::where('created_at', '<', $expiredTime)
                                 ->where('status', 'Menunggu Konfirmasi') // Pastikan hanya yang masih aktif
                                 ->update([
                                     'status'    => 'Expired',
                                     'kd_status' => 'EXP'
                                 ]);

            $this->info("Total mempelai yang di-update: " . $mempelais);
        }
}
