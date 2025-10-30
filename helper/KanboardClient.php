<?php

class KanboardClient
{
    private string $url;
    private string $username;
    private string $token;

    public function __construct(string $url, string $username, string $token)
    {
        $this->url = $url;
        $this->username = $username;
        $this->token = $token;
    }

    /**
     * Zentrale Methode, um JSON-RPC Calls auszuführen
     */
    private function call(string $method, array $params = [], $id = null)
    {
        $data = [
            'jsonrpc' => '2.0',
            'method'  => $method,
            'id'      => $id ?? uniqid(),
            'params'  => $params
        ];

        $json = json_encode($data);

        $ch = curl_init($this->url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CUSTOMREQUEST  => 'POST',
            CURLOPT_POSTFIELDS     => $json,
            CURLOPT_HTTPHEADER     => [
                'Content-Type: application/json',
                'Content-Length: ' . strlen($json)
            ],
            CURLOPT_USERPWD => "{$this->username}:{$this->token}"
        ]);

        $response = curl_exec($ch);

        if ($response === false) {
            $error = curl_error($ch);
            curl_close($ch);
            throw new Exception("cURL-Fehler: $error");
        }

        curl_close($ch);

        $decoded = json_decode($response);

        if (isset($decoded->error)) {
            throw new Exception("Kanboard-API-Fehler: " . json_encode($decoded->error));
        }

        return $decoded->result ?? null;
    }

    /**
     * Beispielmethoden für häufige API-Aufrufe
     */
    public function getAllProjects()
    {
        return $this->call('getAllProjects');
    }

    public function getTaskByReference(int $projectId, string $reference)
    {
        return $this->call('getTaskByReference', [
            'project_id' => $projectId,
            'reference'  => $reference
        ]);
    }

    public function createTask(array $params)
    {
        return $this->call('createTask', $params);
    }

    // Weitere Methoden kannst du leicht ergänzen:
    // public function getTask($taskId) { return $this->call('getTask', ['task_id' => $taskId]); }
    // public function updateTask($taskId, array $fields) { ... }
}
