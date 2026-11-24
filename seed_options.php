<?php
require_once __DIR__ . '/shared/config.php';

$pdo = db();

echo "1. Adding options column to dishes table if not exists...\n";
$pdo->exec("ALTER TABLE dishes ADD COLUMN IF NOT EXISTS options TEXT DEFAULT NULL;");
echo "Column options ready!\n";

echo "2. Seeding options for relevant dishes...\n";

// Burgers options
$burgerOptions = [
    [
        'id' => 'sauce',
        'name' => 'Choix de Sauce',
        'type' => 'single',
        'required' => true,
        'items' => [
            ['name' => 'Sauce Maison Sola (Douce)', 'price' => 0],
            ['name' => 'Sauce Algérienne (Piquante 🌶️)', 'price' => 0],
            ['name' => 'Sauce Barbecue Fumée', 'price' => 0],
            ['name' => 'Sauce Fromagère Onctueuse', 'price' => 5],
        ]
    ],
    [
        'id' => 'cuisson',
        'name' => 'Cuisson de la viande',
        'type' => 'single',
        'required' => true,
        'items' => [
            ['name' => 'À point (Recommandé)', 'price' => 0],
            ['name' => 'Bien cuit', 'price' => 0],
            ['name' => 'Saignant', 'price' => 0],
        ]
    ],
    [
        'id' => 'extras',
        'name' => 'Suppléments & Extras',
        'type' => 'multiple',
        'required' => false,
        'items' => [
            ['name' => 'Double Cheddar Fondu', 'price' => 6],
            ['name' => 'Bacon de Bœuf Croustillant', 'price' => 10],
            ['name' => 'Portion Frites Maison', 'price' => 12],
            ['name' => 'Sans oignons', 'price' => 0],
        ]
    ]
];

// Pizza options
$pizzaOptions = [
    [
        'id' => 'pate',
        'name' => 'Type de Pâte',
        'type' => 'single',
        'required' => true,
        'items' => [
            ['name' => 'Pâte Fine & Croustillante', 'price' => 0],
            ['name' => 'Pâte Moelleuse Napolitaine', 'price' => 0],
            ['name' => 'Bordure Fourrée Fromage', 'price' => 10],
        ]
    ],
    [
        'id' => 'extras_pizza',
        'name' => 'Suppléments Gourmands',
        'type' => 'multiple',
        'required' => false,
        'items' => [
            ['name' => 'Extra Mozzarella di Bufala', 'price' => 10],
            ['name' => 'Huile Pimentée Maison 🌶️', 'price' => 0],
            ['name' => 'Champignons Frais', 'price' => 6],
            ['name' => 'Olives Noires Marinées', 'price' => 4],
        ]
    ]
];

// Pâtes options
$patesOptions = [
    [
        'id' => 'piquant',
        'name' => 'Intensité Pimentée',
        'type' => 'single',
        'required' => true,
        'items' => [
            ['name' => 'Doux (Sans piquant)', 'price' => 0],
            ['name' => 'Modéré 🌶️', 'price' => 0],
            ['name' => 'Épicé Relevé 🌶️🌶️', 'price' => 0],
        ]
    ],
    [
        'id' => 'fromage_pates',
        'name' => 'Finition Fromage',
        'type' => 'single',
        'required' => false,
        'items' => [
            ['name' => 'Parmesan Râpé Frais', 'price' => 5],
            ['name' => 'Sans fromage', 'price' => 0],
        ]
    ]
];

// Salades options
$saladeOptions = [
    [
        'id' => 'vinaigrette',
        'name' => 'Vinaigrette & Sauce',
        'type' => 'single',
        'required' => true,
        'items' => [
            ['name' => 'Sauce César Traditionnelle', 'price' => 0],
            ['name' => 'Vinaigrette Balsamique & Miel', 'price' => 0],
            ['name' => 'Huile d’Olive Extra Vierge & Citron', 'price' => 0],
        ]
    ],
    [
        'id' => 'extras_salade',
        'name' => 'Suppléments',
        'type' => 'multiple',
        'required' => false,
        'items' => [
            ['name' => 'Poulet Grillé Émincé', 'price' => 12],
            ['name' => 'Croûtons Dorés à l’Ail', 'price' => 4],
            ['name' => 'Copeaux de Parmesan', 'price' => 6],
        ]
    ]
];

// Café options
$cafeOptions = [
    [
        'id' => 'intensite',
        'name' => 'Type d’extraction',
        'type' => 'single',
        'required' => true,
        'items' => [
            ['name' => 'Espresso Court (Serré)', 'price' => 0],
            ['name' => 'Espresso Normal', 'price' => 0],
            ['name' => 'Lungo (Allongé)', 'price' => 0],
        ]
    ],
    [
        'id' => 'sucre',
        'name' => 'Sucre',
        'type' => 'single',
        'required' => false,
        'items' => [
            ['name' => 'Sans sucre', 'price' => 0],
            ['name' => '1 Sucre', 'price' => 0],
            ['name' => '2 Sucres', 'price' => 0],
        ]
    ]
];

// Limonade options
$limonadeOptions = [
    [
        'id' => 'saveur',
        'name' => 'Variante de saveur',
        'type' => 'single',
        'required' => true,
        'items' => [
            ['name' => 'Citron & Menthe Fraîche', 'price' => 0],
            ['name' => 'Citron & Fruits Rouges', 'price' => 5],
            ['name' => 'Citron & Gingembre', 'price' => 5],
        ]
    ],
    [
        'id' => 'sucre_boisson',
        'name' => 'Niveau de sucre',
        'type' => 'single',
        'required' => true,
        'items' => [
            ['name' => 'Normal', 'price' => 0],
            ['name' => 'Peu sucré', 'price' => 0],
            ['name' => 'Sans sucre ajouté', 'price' => 0],
        ]
    ]
];

$stmt = $pdo->prepare("UPDATE dishes SET options = ? WHERE id = ?");

// Update Burgers (IDs 12, 13, 14)
$stmt->execute([json_encode($burgerOptions, JSON_UNESCAPED_UNICODE), 12]);
$stmt->execute([json_encode($burgerOptions, JSON_UNESCAPED_UNICODE), 13]);
$stmt->execute([json_encode($burgerOptions, JSON_UNESCAPED_UNICODE), 14]);

// Update Pizzas (IDs 15, 16, 17)
$stmt->execute([json_encode($pizzaOptions, JSON_UNESCAPED_UNICODE), 15]);
$stmt->execute([json_encode($pizzaOptions, JSON_UNESCAPED_UNICODE), 16]);
$stmt->execute([json_encode($pizzaOptions, JSON_UNESCAPED_UNICODE), 17]);

// Update Pâtes (IDs 18, 19, 20)
$stmt->execute([json_encode($patesOptions, JSON_UNESCAPED_UNICODE), 18]);
$stmt->execute([json_encode($patesOptions, JSON_UNESCAPED_UNICODE), 19]);
$stmt->execute([json_encode($patesOptions, JSON_UNESCAPED_UNICODE), 20]);

// Update Salades (IDs 21, 22)
$stmt->execute([json_encode($saladeOptions, JSON_UNESCAPED_UNICODE), 21]);
$stmt->execute([json_encode($saladeOptions, JSON_UNESCAPED_UNICODE), 22]);

// Update Limonade (ID 25)
$stmt->execute([json_encode($limonadeOptions, JSON_UNESCAPED_UNICODE), 25]);

// Update Café (ID 27)
$stmt->execute([json_encode($cafeOptions, JSON_UNESCAPED_UNICODE), 27]);

// Note: IDs 23 (Tiramisu), 24 (Fondant), 26 (Ice Tea) have NO options (NULL)! Exactly as requested: "machi f kulchi, kaynin chi plats li ma3ndhomch khasa2is"!
$pdo->exec("UPDATE dishes SET options = NULL WHERE id IN (23, 24, 26);");

echo "Seeded options successfully!\n";
