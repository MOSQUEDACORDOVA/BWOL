<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Twilio\Rest\Client as ClientWhatsApp;
use GuzzleHttp\Client;

class WhatsAppController extends Controller
{
    public function receiveMessage(Request $request)
    {
    
        $from = $request['From']; // Número de quien envía el mensaje
        $body = $request['Body']; // Cuerpo del mensaje recibido

        // Llamar a ChatGPT
        $this->chatGpt($body, $from);

        return response()->json(['status' => 'Message received and processed']);
    }

    public function chatGpt(string $promt, string $from)
    {
        
        $apiKey = env('OPENAI_API_KEY');
        
        $client = new Client();
        try {
            $response = $client->request('POST', 'https://api.openai.com/v1/chat/completions', [
                'headers' => [
                    'Authorization' => 'Bearer ' . $apiKey,
                    'Content-Type' => 'application/json',
                ],
                'json' => [
                    'model' => 'gpt-3.5-turbo', // Cambia a 'gpt-4' si tienes acceso a este modelo
                    'messages' => [
                        ['role' => 'user', 'content' => $promt],
                    ],
                ],
            ]);

            $responseData = json_decode($response->getBody(), true);
            $reply = $responseData['choices'][0]['message']['content'];

            // Llamar a la función para enviar respuesta
            $this->sendWhatsAppMessage($reply, $from);

            return response()->json(['reply' => $reply]);

        } catch (\Exception $e) {
            \Log::error('Error contacting OpenAI API: ' . $e->getMessage());
            return response()->json(['error' => 'Error al comunicarse con la API'], 500);
        }
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
