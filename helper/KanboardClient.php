<?php

class KanboardClient
{
    private string $url;
    private string $username;
    private string $token;
    private string $ssl_verifypeer;

    private string $jsonrpc_php = "jsonrpc.php";

    public function __construct(string $url, string $username, string $token, bool $ssl_verifypeer = true) {
        $this->url = $url;
        $this->username = $username;
        $this->token = $token;
        $this->ssl_verifypeer = $ssl_verifypeer;
    }

    /**
     * Zentrale Methode, um JSON-RPC Calls auszuführen
     */
    private function call(string $method, array $params = [], $id = null) {
        $data = [
            'jsonrpc' => '2.0',
            'method'  => $method,
            'id'      => $id ?? uniqid(),
            'params'  => $params
        ];

        $json = json_encode($data);

        $ch = curl_init($this->url.'/'.rtrim($this->jsonrpc_php, '/'));
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CUSTOMREQUEST  => 'POST',
            CURLOPT_POSTFIELDS     => $json,
            CURLOPT_SSL_VERIFYPEER => $this->ssl_verifypeer,
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
    public function getAllProjects() {
        return $this->call('getAllProjects');
    }

    public function getActiveTaskByReference(int $projectId, string $reference) {
        $activeTasks =  $this->call('getAllTasks', [
            'project_id' => $projectId,
            'status_id'  => 1
        ]);

        foreach ($activeTasks as $task) {
            if ($task->reference === $reference) {
                return $task;
            }
        }
    }

    /**
     * Holt von Kanboard alle Tasks, deren Fälligkeitsdatum heute ist oder in der Zukunft liegt.
     * @param int $projectId
     * @param string $reference
     * @return array Ein Array von Task-Objekten - gibt es keine, dann ein leeres Array zurück
     */
    public function getTaskWithUnreachedDueDateByReference(int $projectId, string $reference) {
        $params = [
            'project_id' => $projectId,
            'query'  => "due:>=today reference:$reference"
        ];
        $tasks =  $this->call('searchTasks', $params);

        return $tasks;
    }
    
    public function getUserByName(string $username) {
        $params = [
            'username' => $username
        ];
        return $this->call('getUserByName', $params);
    }

    public function createTask(string $pagetitle, int $project_id, int $owner_id, string $kanboard_reference, string $date_due) {
        $params = [
            'title' => $pagetitle,
            'project_id' => $project_id,
            'owner_id' => $owner_id,
            'reference' => $kanboard_reference,
            'date_due' => $date_due
        ];

        return $this->call('createTask', $params);
    }
    
    public function closeTask(string $taskid) {
        $params = [
            'task_id' => $taskid
        ];
        return $this->call('closeTask', $params);
    }

    public function dateToString(DateTime $dateTime) {
        return $dateTime->format('Y-m-d H:i');
    }
}
