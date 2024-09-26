<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Client; // Asegúrate de tener un modelo Client
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;

class CheckInsuranceExpiry extends Command
{
    // El nombre y descripción del comando en Artisan
    protected $signature = 'insurance:check-expiry';
    protected $description = 'Check which clients have insurance expiring in the next 5 days';

    public function __construct()
    {
        parent::__construct();
    }
    
    public function handle()
    {
        // Obtener la fecha actual
        $today = Carbon::now();
        
        // Obtener la fecha dentro de 5 días
        $nextFiveDays = $today->addDays(5);
        
        // Consultar los clientes cuyo seguro vence en los próximos 5 días
        $clients = Client::whereBetween('insurance_expiry_date', [now(), $nextFiveDays])
                         ->get();

        if ($clients->isEmpty()) {
            $this->info('No clients with insurance expiring in the next 5 days.');
        } else {
            foreach ($clients as $client) {
                // Aquí puedes agregar tu lógica para notificar al cliente o hacer otra acción
                // Ejemplo: Guardar en un log o enviar un mensaje
                Log::info("Client {$client->name} with WhatsApp number {$client->whatsapp_number} has insurance expiring on {$client->insurance_expiry_date}.");
                
                // Otras acciones como enviar un mensaje por WhatsApp, correo, etc.
            }

            $this->info('Checked clients with expiring insurance.');
        }
    }
}
