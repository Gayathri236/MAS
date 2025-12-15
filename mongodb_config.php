<?php

require __DIR__ . '/vendor/autoload.php';  
use MongoDB\Client; 

try {
    // Connect to MongoDB
    $client = new Client("mongodb://localhost:27017");

    // Database
    $db = $client->Market;

    // Collections
    $usersCollection = $db->users;         
    $productsCollection = $db->products;   
    $ordersCollection = $db->orders;
    $workersCollection = $db->workers;
    $vehiclesCollection = $db->vehicles;
    $timeSlotsCollection = $db->timeslot; 
} catch (Exception $e) {
    die("MongoDB connection failed: " . $e->getMessage());
}
?>


