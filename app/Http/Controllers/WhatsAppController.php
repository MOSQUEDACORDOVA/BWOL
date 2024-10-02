<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Twilio\Rest\Client as ClientWhatsApp;
use GuzzleHttp\Client;
use App\Models\ConversationHistory; // Modelo para la tabla del historial

class WhatsAppController extends Controller
{
    public function receiveMessage(Request $request)
    {
    
        $from = $request['From']; // Número de quien envía el mensaje
        $body = $request['Body']; // Cuerpo del mensaje recibido

        // Guardar el mensaje del usuario en la base de datos
        $this->storeMessage($from, 'user', $body);

        // Llamar a ChatGPT
        $this->chatGpt($body, $from);

        return response()->json(['status' => 'Message received and processed']);
    }

    public function chatGpt(string $promt, string $from)
    {
        
        $apiKey = env('OPENAI_API_KEY');
        
        $client = new Client();
        // Obtener el historial de mensajes desde la base de datos
        $chatHistory = $this->getChatHistory($from);

        // Añadir el mensaje del sistema desde el archivo de configuración
        $systemMessage = [
            'role' => 'system',
            'content' => config('openai.system_message'), // Obtener el mensaje desde el archivo de configuración
        ];

        // Añadir el mensaje del sistema al historial de chat
        $chatHistory[] = $systemMessage;
        
        // Añadir el nuevo mensaje del usuario al historial
        $chatHistory[] = ['role' => 'user', 'content' => $promt];

        try {
            $response = $client->request('POST', 'https://api.openai.com/v1/chat/completions', [
                'headers' => [
                    'Authorization' => 'Bearer ' . $apiKey,
                    'Content-Type' => 'application/json',
                ],
                'json' => [
                    'model' => 'gpt-4o', // Cambiar a 'gpt-4' si es necesario
                    'messages' => $chatHistory, // Enviar el historial completo
                ],
            ]);

            $responseData = json_decode($response->getBody(), true);
            $reply = $responseData['choices'][0]['message']['content'];

            // Registrar la respuesta para debugging
            \Log::info('Respuesta de ChatGPT: ' . $reply);

            // Verificar si ChatGPT solicitó una consulta a la base de datos
            if (preg_match('/\{query_database:"([^"]+)"\}/', $reply, $matches)) {
                $condition = $matches[1];
                \Log::info('Condición encontrada: ' . $condition);

                // Añadir comillas simples a los valores de cadena si es necesario
                if (preg_match('/=([a-zA-Z\s]+)/', $condition, $valueMatches)) {
                    // Asegurarse de que el valor esté entre comillas simples
                    $value = $valueMatches[1];
                    $condition = str_replace($value, "'$value'", $condition);
                }

                \Log::info('Condición SQL ajustada: ' . $condition);

                // Realizar la consulta a la base de datos
                $data = \DB::table('properties')->whereRaw($condition)->get();

                \Log::info('Respuesta SQL: ' . $data);

                if ($data->isEmpty() || $data->count() === 0) {
                    $chatHistory[] = ['role' => 'system', 'content' => 'SYSTEMA-666: No tenemos esa información.'];

                    // Volver a llamar a ChatGPT para que genere una respuesta utilizando esos datos
                    $response = $client->request('POST', 'https://api.openai.com/v1/chat/completions', [
                        'headers' => [
                            'Authorization' => 'Bearer ' . $apiKey,
                            'Content-Type' => 'application/json',
                        ],
                        'json' => [
                            'model' => 'gpt-3.5-turbo',
                            'messages' => $chatHistory,
                        ],
                    ]);

                    $responseData = json_decode($response->getBody(), true);
                    $reply = $responseData['choices'][0]['message']['content'];

                } else {

                    // Añadir los resultados obtenidos como un nuevo mensaje en el historial
                    $chatHistory[] = ['role' => 'system', 'content' => 'SYSTEMA-666: Responde con el prefijo 333:' . $data];

                    // Volver a llamar a ChatGPT para que genere una respuesta utilizando esos datos
                    $response = $client->request('POST', 'https://api.openai.com/v1/chat/completions', [
                        'headers' => [
                            'Authorization' => 'Bearer ' . $apiKey,
                            'Content-Type' => 'application/json',
                        ],
                        'json' => [
                            'model' => 'gpt-3.5-turbo',
                            'messages' => $chatHistory,
                        ],
                    ]);

                    $responseData = json_decode($response->getBody(), true);
                    $reply = $responseData['choices'][0]['message']['content'];
                }
            }

            // Guardar la respuesta del asistente en la base de datos
            $this->storeMessage($from, 'assistant', $reply);

            // Enviar la respuesta vía WhatsApp
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

    // Función para almacenar mensajes en la base de datos
    private function storeMessage(string $userPhone, string $role, string $message)
    {
        ConversationHistory::create([
            'user_phone' => $userPhone,
            'role' => $role,
            'message' => $message,
        ]);
    }

    // Función para recuperar el historial de conversación de la base de datos
    private function getChatHistory(string $userPhone)
    {
        // Obtener todos los mensajes previos de este usuario ordenados por creación
        $history = ConversationHistory::where('user_phone', $userPhone)
            ->orderBy('created_at', 'asc')
            ->get(['role', 'message']);

        // Convertir el formato de los mensajes para enviarlos a la API de OpenAI
        return $history->map(function ($message) {
            return [
                'role' => $message->role,
                'content' => $message->message,
            ];
        })->toArray();
    }
}
