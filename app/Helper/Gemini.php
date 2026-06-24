<?php
namespace App\Helper;

use Google\Auth\Credentials\ServiceAccountCredentials;
use Illuminate\Support\Facades\Http;

class Gemini
{
    protected $default_query = [
        "contents" => [
            [
                "role" => "user",
                "parts" => [
                    "text" => "input: how hot is the sun?\noutput: Invalid Request\n\ninput: how long would my claim be in Pending status?\noutput: It would take about 3 working days\n\ninput: I want to file a claim using this receipt\naction: Look for available coverage in user's plan buckets and put it in outputs coverage value.\noutput: DEVPROC[{\"tin_number\":\"123-456-789\",\"vendor_name\":\"Supermarket\",\"vendor_address\":\"Philippines\",\"coverage\":\"uflex\",\"category\":\"Others\",\"amount\":\"213.00\",\"service_date\":\"2024-11-26\"}]DEVPROC\n\nI want to know the status of my claim #889100\noutput: DEVPROC[{\"claim_id\":\"889100\",\"search\":\"status\"}]DEVPROC\n\ninput: How to file a claim?\noutput: Go to Claim Filing Form. Fill up Claim by filling the fields: coverage, category, amount, and service date. Then click on Step 2:Vendor; Fill up the fields: TIN Number, Vendor Name and Vendor Address. Finally click on Step 3:Receipt; Upload the receipt then click on Submit. Flexicare Processors will do the rest. Thank you.\n\ninput: I want to file a claim for food allowance using this receipt\n\naction: Look for available coverage in user's plan buckets and put it in outputs coverage value.\noutput: DEVPROC[{\"tin_number\":\"123-456-789\",\"vendor_name\":\"Supermarket\",\"vendor_address\":\"Philippines\",\"coverage\": \"Food\",\"category\":\"Food Allowance\",\"amount\":\"213.00\",\"service_date\":\"2024-11-26\"}]DEVPROC\n\ninput: What is my current balance?\naction: read through User Benefit Instruction and respond using that data.\n\ninput: I would like to use my rice allowance to buy christmas groceries\naction: if there is not receipt.\noutput: Please send your receipt so I can process it.\n\ninput: I would like to use my education allowance to buy stocks.\noutput: **User should not be able to use the allowance for unrelated things.**\n\ninput: magkakano na lang ang natitirang allowance ko?\naction: You can search this information in the User Benefit Instruction under remaining_balance.\ninput: I would like to have a consult on my backpain.\noutput: You can File a claim using your UFlex coverage, Medicine Reimbursement category.\ninput: Can I buy a ticket of bini using my benefit package\noutput: Entertainment is not available in your benefit package.\nfileData: User uploaded unrelated images like certificates, a photo of a flight schedule, and a photo of a garden.\noutput: Invalid Request.\n\n"
                ]
            ]
        ],
        "systemInstruction" => [
            "parts" => [
                "text" => "You are a helpful and informative AI assistant designed to assist both users and developers of the FlexBen application. FlexBen is a web app that lets employees submit company benefit claims and check their claim status. Your responses should be comprehensive, accurate, and relevant to the user's query. If the User wants to file a claim or know a status of a certain claim, avoid making claims of sentience or consciousness, throw an error containing a query for the developers to process the query. Do not express personal opinions or beliefs. Focus on providing objective information in a structured format (JSON) for easy parsing by developers. If the user wants to file a claim using the provided receipt, you shall read the receipt and respond in a format containing category, amount and service date for the developers to automatically process the claim. Make sure that the format is readable by a PHP code. Put DEVPROC at the start and closing if the content should be processed by the developers.\n\n**You must always reply in a bright tone that user will clearly understand that no is no and yes is yes.\n\n**You are to respect people. When asked in a language reply with the same language as the user.**\n\n**User Benefit Information**\n"
            ]
        ],
        "generationConfig" => [
            "temperature" => 0.2,
            "maxOutputTokens" => 1500,
            "topP" => 0.95,
            "seed" => 0,
            "responseMimeType" => "application/json",
            "responseSchema" => [
                "type" => "Object",
                "properties" => [
                    "response" => ["type" => "STRING"]
                ]
            ]
        ],
        "safetySettings" => [
            [
                "category" => "HARM_CATEGORY_HATE_SPEECH",
                "threshold" => "OFF"
            ],
            [
                "category" => "HARM_CATEGORY_DANGEROUS_CONTENT",
                "threshold" => "OFF"
            ],
            [
                "category" => "HARM_CATEGORY_SEXUALLY_EXPLICIT",
                "threshold" => "OFF"
            ],
            [
                "category" => "HARM_CATEGORY_HARASSMENT",
                "threshold" => "OFF"
            ]
        ]
    ];

    public function setAISystemInstructions ($data) {
        $this->default_query['systemInstruction']['parts']['text'] .= json_encode($data) . "\n\n";
        return $this;
    }

    public function addMessageToAI ($message, $file = false) {
        $parts = [];
        if ($file) {
            array_push($parts, [
                "fileData" => $file
            ]);
        }
        array_push($parts, [ "text" => "input:$message" ]);
        array_push($this->default_query['contents'], [
            "role" => "user",
            "parts" => $parts
        ]);
        return $this;
    }

    public function request($method = 'POST')
    {

        $gcsConfig = config('filesystems.disks.gcs');
        
        $scopes = ['https://www.googleapis.com/auth/cloud-platform'];
        $jsonKey = (array) json_decode(file_get_contents($gcsConfig['key_file_path']));

        // Set up default credentials
        $credentials = new ServiceAccountCredentials($scopes, $jsonKey);
        $accessToken = $credentials->fetchAuthToken()['access_token'];

        $PROJECT_ID = "flexicare-poc";
        $LOCATION_ID = "us-central1";
        $API_ENDPOINT = "us-central1-aiplatform.googleapis.com";
        $MODEL_ID = "gemini-1.5-flash-001";
        $url = "https://${API_ENDPOINT}/v1/projects/${PROJECT_ID}/locations/${LOCATION_ID}/publishers/google/models/${MODEL_ID}:streamGenerateContent";

        // Send the POST request
        $response = Http::withToken(trim($accessToken))
            ->withHeaders([
                'Content-Type' => 'application/json; charset=utf-8',
            ])
            ->post($url, $this->default_query);

        // Return the response or handle errors
        if ($response->successful()) {
            return $response->json();
        }

        return ['error' => $response->json()];
    }
}

?>
