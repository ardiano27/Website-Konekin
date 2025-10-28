<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');

// Fungsi untuk mengambil data dari URL eksternal menggunakan cURL
function fetch_pokeapi_data($url) {
    // Inisialisasi cURL
    $ch = curl_init();

    // Mengatur opsi cURL
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true); // Mengembalikan transfer sebagai string
    curl_setopt($ch, CURLOPT_TIMEOUT, 10); // Batas waktu 10 detik

    // Eksekusi cURL
    $response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $error = curl_error($ch);

    // Tutup cURL
    curl_close($ch);

    // Penanganan error jaringan/cURL
    if ($response === false || $http_code !== 200) {
        return [
            'status' => 'error',
            'message' => 'Gagal mengambil data dari PokeAPI. Kode HTTP: ' . $http_code . '. Error cURL: ' . $error,
            'http_code' => ($http_code !== 0) ? $http_code : 503
        ];
    }

    // Mendekode JSON
    $data = json_decode($response, true);

    if (json_last_error() !== JSON_ERROR_NONE) {
        return [
            'status' => 'error',
            'message' => 'Gagal memproses respons JSON dari PokeAPI.',
            'http_code' => 500
        ];
    }

    return [
        'status' => 'success',
        'data' => $data,
        'http_code' => 200
    ];
}

// ===========================================
// MAIN LOGIC
// ===========================================

// 1. Ambil nama/ID Pokemon dari Query Parameter (Misal: ?pokemon=pikachu atau ?pokemon=25)
// Default-nya adalah ID 1 (Bulbasaur) jika tidak ada yang dimasukkan.
$pokemon_identifier = $_GET['pokemon'] ?? '1'; 

// 2. Tentukan URL PokeAPI
// Gunakan endpoint untuk detail Pokemon: https://pokeapi.co/api/v2/pokemon/{id atau nama}
$pokeapi_url = "https://pokeapi.co/api/v2/pokemon/" . strtolower($pokemon_identifier);

// 3. Ambil data
$result = fetch_pokeapi_data($pokeapi_url);

// 4. Kirim respons API Anda sendiri
http_response_code($result['http_code']);

if ($result['status'] === 'success') {
    // Sederhanakan data untuk respons API Anda
    $pokemon_data = $result['data'];
    
    echo json_encode([
        'status' => 'success',
        'message' => 'Data Pokemon berhasil diambil.',
        'name' => $pokemon_data['name'],
        'id' => $pokemon_data['id'],
        'height' => $pokemon_data['height'],
        'weight' => $pokemon_data['weight'],
        'abilities' => array_column($pokemon_data['abilities'], 'ability'),
        'sprite_url' => $pokemon_data['sprites']['front_default'] ?? null
    ]);
} else {
    // Kirim error jika gagal
    echo json_encode([
        'status' => 'error',
        'message' => $result['message']
    ]);
}

?>