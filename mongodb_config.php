<?php
/*
// mongodb_config.php

require __DIR__ . '/vendor/autoload.php';  // Load Composer autoloader

use MongoDB\Client;  // Import MongoDB Client

try {
    $mongoClient = new Client("mongodb://localhost:27017");  // Connect to MongoDB
    $database = $mongoClient->Market;   // Database
    $collection = $database->users;     // Users collection (use $collection for consistency)
} catch (Exception $e) {
    die("MongoDB connection failed: " . $e->getMessage());
}
    */



// mongodb_config.php

require __DIR__ . '/vendor/autoload.php';  // Load Composer autoloader
use MongoDB\Client;  // Import MongoDB Client

try {
    // Connect to MongoDB
    $client = new Client("mongodb://localhost:27017");

    // Database
    $db = $client->Market;

    // Collections
    $usersCollection = $db->users;         // For users
    $productsCollection = $db->products;   // For products
    $ordersCollection = $db->orders;
    $workersCollection = $db->workers;
    $vehiclesCollection = $db->vehicles;
    $timeSlotsCollection = $db->time_slots; 
} catch (Exception $e) {
    die("MongoDB connection failed: " . $e->getMessage());
}
?>


