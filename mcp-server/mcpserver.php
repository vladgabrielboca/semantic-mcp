<?php
require __DIR__ . '/vendor/autoload.php';
require __DIR__ . '/.env.php';

header("Content-Type: application/json");

$descriereMetode = file_get_contents(__DIR__ . "/../mcp-proxy/tooldescriptions.json");

$mapareMetode = [
    "getTeamByID"        => "obtineEchipa",
    "getTeamsByLeague"   => "obtineEchipeLeague",
    "getPlayerByID"      => "obtineJucator",
    "getPlayersByTeamID" => "obtineJucatoriEchipa",
    "createPlayer"       => "creeazaJucator",
];

$clientJSONServer = new \GuzzleHttp\Client(['verify' => false]);

$cerereSosita = json_decode(file_get_contents("php://input"), true);
$idRpc        = $cerereSosita["id"];
$metodaRpc    = $cerereSosita["method"];
$paramsRpc    = $cerereSosita["params"] ?? [];

switch ($metodaRpc) {

    case "tools/list":
        print json_encode([
            "jsonrpc" => "2.0",
            "id"      => $idRpc,
            "result"  => ["tools" => $descriereMetode]
        ]);
        break;

    case "tools/call":
        $numeTool    = $paramsRpc["name"];
        $parametriTool = $paramsRpc["arguments"];

        if (!isset($mapareMetode[$numeTool])) {
            print json_encode(["jsonrpc" => "2.0", "id" => $idRpc,
                "error" => ["code" => -32601, "message" => "Tool not found: $numeTool"]]);
            break;
        }

        $functia     = $mapareMetode[$numeTool];
        $rezultat    = $functia($clientJSONServer, $parametriTool);

        print json_encode([
            "jsonrpc" => "2.0",
            "id"      => $idRpc,
            "result"  => $rezultat
        ]);
        break;

    default:
        print json_encode(["jsonrpc" => "2.0", "id" => $idRpc,
            "error" => ["code" => -32601, "message" => "Method not found"]]);
}

function obtineEchipa($guzzle, $params) {
    if (!isset($params["id"])) return ["error" => "Missing id"];
    $r = $guzzle->request("GET", "http://localhost:4000/teams/" . urlencode($params["id"]));
    return json_decode($r->getBody(), true);
}

function obtineEchipeLeague($guzzle, $params) {
    if (!isset($params["league"])) return ["error" => "Missing league"];
    $r = $guzzle->request("GET", "http://localhost:4000/teams?league=" . urlencode($params["league"]));
    return json_decode($r->getBody(), true);
}

function obtineJucator($guzzle, $params) {
    if (!isset($params["id"])) return ["error" => "Missing id"];
    $r = $guzzle->request("GET", "http://localhost:4000/players/" . urlencode($params["id"]));
    return json_decode($r->getBody(), true);
}

function obtineJucatoriEchipa($guzzle, $params) {
    if (!isset($params["teamId"])) return ["error" => "Missing teamId"];
    $r = $guzzle->request("GET", "http://localhost:4000/players?teamId=" . urlencode($params["teamId"]));
    return json_decode($r->getBody(), true);
}

function creeazaJucator($guzzle, $params) {
    if (!isset($params["player"])) return ["error" => "Missing player data"];
    $r = $guzzle->request("POST", "http://localhost:4000/players", [
        "json"    => $params["player"],
        "headers" => ["Content-Type" => "application/json"]
    ]);
    return json_decode($r->getBody(), true);
}
?>