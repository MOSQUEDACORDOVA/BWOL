<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Twilio\Rest\Client as ClientWhatsApp;

class WhatsAppController extends Controller
{
    public function receiveMessage(Request $request)
    {
        $validated = $request->validate([
            'From' => 'required|string',
            'Body' => 'required|string',
        ]);
    
        $from = $validated['From']; // Número de quien envía el mensaje
        $body = $validated['Body']; // Cuerpo del mensaje recibido

        // Aquí puedes procesar el mensaje recibido. Por ejemplo:
        if (strtolower($body) == 'hola') {
            $responseMessage = '¡Hola! ¿Cómo puedo ayudarte hoy?';
        } else {
            $responseMessage = 'Gracias por tu mensaje, pronto te contactaremos.';
        }

        // Llamar a la función para enviar respuesta
        $this->sendWhatsAppMessage($responseMessage, $from);

        return response()->json(['status' => 'Message received and processed']);
    }

    public function sendWhatsAppMessage(string $message, string $recipient)
    {
        try {
            $twilio_whatsapp_number = env('TWILIO_WHATSAPP_NUMBER');
            $account_sid = env("TWILIO_SID");
            $auth_token = env("TWILIO_AUTH_TOKEN");

            $client = new ClientWhatsApp($account_sid, $auth_token);
            return $client->messages->create($recipient, [
                'from' => "whatsapp:$twilio_whatsapp_number",
                'body' => $message,
            ]);
        } catch (\Exception $e) {
            // Maneja el error según sea necesario
            \Log::error("Error sending WhatsApp message: " . $e->getMessage());
            return response()->json(['error' => 'Failed to send message'], 500);
        }
    }
}
