<?php
require __DIR__ . '/vendor/autoload.php';
require __DIR__ . '/.env.php';

$clientGLM = new \GuzzleHttp\Client(['verify' => false]);
$clientMCP = new \GuzzleHttp\Client(['verify' => false]);

$userPrompt = $_GET['prompt'] ?? '';

$tooluriRaspuns = $clientMCP->request("POST", "http://localhost:8000/mcpserver.php", [
    "json" => ["jsonrpc" => "2.0", "id" => 1, "method" => "tools/list"]
]);

$tooluriDecodat  = json_decode($tooluriRaspuns->getBody(), true);
$descriereMetode = json_decode($tooluriDecodat["result"]["tools"], true);

$toolsOpenAI = array_map(function($tool) {
    return [
        "type" => "function",
        "function" => [
            "name"        => $tool["name"],
            "description" => $tool["description"],
            "parameters"  => [
                "type"       => "object",
                "properties" => array_reduce(
                    $tool["params"],
                    function($carry, $param) {
                        $carry[$param["name"]] = $param["schema"] + ["description" => $param["description"]];
                        return $carry;
                    },
                    []
                ),
                "required" => array_map(
                    fn($p) => $p["name"],
                    array_filter($tool["params"], fn($p) => $p["required"])
                )
            ]
        ]
    ];
}, $descriereMetode);

$adresa = "https://api.z.ai/api/coding/paas/v4/chat/completions";
$cheie  = GLM_API_KEY;
$antete = [
    'Authorization' => "Bearer " . $cheie,
    'Content-Type'  => 'application/json'
];

$mesaje = [
    [
        'role'    => 'system',
        'content' => 'You are an assistant that can ONLY answer questions about football teams and players. ' .
                     'Use tools only when the user provides required parameters explicitly. ' .
                     'If a required parameter is missing, ask the user to provide it. ' .
                     'If the question is unrelated, respond: "I can only answer questions about football teams and players." ' .
                     'Do not invent IDs or parameter values.'
    ],
    ['role' => 'user', 'content' => $userPrompt]
];

$date = [
    'model'       => 'glm-5.1',
    'messages'    => $mesaje,
    'tools'       => $toolsOpenAI,
    'tool_choice' => 'auto'
];

$raspunsLLM      = $clientGLM->request("POST", $adresa, ["headers" => $antete, "json" => $date]);
$raspunsDecodat  = json_decode($raspunsLLM->getBody(), true);
$mesajRecomandare = $raspunsDecodat['choices'][0]['message'];

if (!isset($mesajRecomandare['tool_calls'])) {
    print $mesajRecomandare['content'];
    exit;
}

$toolRecomandat = $mesajRecomandare['tool_calls'][0];
$numeTool       = $toolRecomandat["function"]["name"];
$parametriTool  = json_decode($toolRecomandat["function"]["arguments"], true);

$raspunsToolExec = $clientMCP->request("POST", "http://localhost:8000/mcpserver.php", [
    "json" => [
        "jsonrpc" => "2.0",
        "id"      => 2,
        "method"  => "tools/call",
        "params"  => ["name" => $numeTool, "arguments" => $parametriTool]
    ]
]);

$rezultatTool = json_decode($raspunsToolExec->getBody(), true)["result"];

$date2 = [
    'model'    => 'glm-5.1',
    'messages' => [
        ['role' => 'user', 'content' => $userPrompt],
        $mesajRecomandare,
        [
            'role'         => 'tool',
            'tool_call_id' => $toolRecomandat["id"],
            'content'      => json_encode($rezultatTool)
        ]
    ]
];

$raspunsLLM2  = $clientGLM->request("POST", $adresa, ["headers" => $antete, "json" => $date2]);
$raspunsFinal = json_decode($raspunsLLM2->getBody(), true);
print $raspunsFinal['choices'][0]['message']['content'];
?>