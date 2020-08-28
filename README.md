# ploc-api-php-client

🇫🇷 Installation et paramétrage 🇫🇷

L'intégration se déroule en 3 étapes : 

- La première est de générer un lien pour lier son compte marchand à son compte PLOC, la méthode attend en paramètre une référence client unique (Identifiant, md5 de l'adresse email...)
Ce lien pointe vers nos serveurs, le client s’identifiera avec ses accès PLOC puis en cas de succès sera redirigé vers la page de destination.

- La seconde est de récupérer les informations, de les vérifier et d’enregistrer le jeton PLOC dans le système.
*C'est ce jeton qui sera utilisé pour communiquer avec le PLOC💙*

- La dernière étape est d’envoyer la liste des vins commandés, cela se fait généralement au moment de l’expédition ou de la réception du colis et également dans l’historique des commandes du client (s'il souhaite recevoir des vins déjà commandés par exemple).



# Étape 1 : Ajouter le bouton de liaison dans l'espace client
La méthode getFollowLink() prend en paramètre un identifiant. Par exemple la référence client ou le md5 de l'adresse email.
```
$client = new PLOC();
echo "<center><button type=button onclick=\"document.location.href='".$client->getFollowLink("Your Customer Reference")."';\">Lier mon compte PLOC</button></center>";

// Lorsque la demande de liaison est faite à partir de PLOC, les informations du PLOC💙 sont stockées dans l'URL.
// Dans ce cas le PLOC💙 est automatiquement redirigé.
if($client->containsUserCredentials()) {
    echo "<script>document.location.href='".$client->getFollowLink("Your Customer Reference")."';</script>";
}
```

> Le PLOC💙 est redirigé sur les serveurs PLOC ou il entre ses identifiants PLOC.
> Puis il est redirigé vers votre page de retour.



# Étape 2 : Réceptionner et stocker le jeton PLOC dans votre système

```
$client = new PLOC();
$isValidFollowLink = $client->isValidFollowLinkUsingCurrentURI();
if(!$isValidFollowLink) {
    echo("<center><font color=red>Something went wrong...</font></center>");
    exit();
}

$plocToken = $client->getCurrentPlocToken();
$appToken = $client->getCurrentAppToken(); // Contient la référence client passée à l'étape 1.
// UPDATE <customerTable> set PLOC = <$plocToken> where id (or md5(email)) = <$appToken>

// On renvoie le PLOC💙 vers l'application
$client->redirectToPloc();
// Cette méthode ne fait rien si la liaison a été initié à partir de l'espace client du site Internet.
```



# Étape 3 : Envoyer le contenu de la commande
> L'envoi de la notification se fait généralement à l'expédition de la commande.

```
// TODO Récupérer le jeton précédemment stocké
$plocToken = '';

if($plocToken == '') {
    echo("<center><font color=red>plocToken is undefined..</font></center>");
    exit();
}

$text = "Bonjour,\nVoici la liste des fiches vins achetées.\nÀ bientôt.";
$purchaseDate = "2020-05-25";
$vendor = array(
	"title" => "PLOC",
	"contact" => "Matthieu Ducrocq",
	"address1" => "2 Ter rue de la Batterie",
	"postalCode" => "62000",
	"city" => "Arras",
	"phoneNumber" => "01 01 01 01 01",
	"email" => "lapaire@PLOC.co",
	"website" => "https://www.PLOC.co"
);
$product1 = array (
    "sku" => "W0002",
    "vintage" => 2012,
    "title" => "Château Fleur Cardinale",
    "color" => "red", // red, rosy, white, sparkling, sweet, other
    "owner" => array(
        "title" => "Château Fleur Cardinale",
        "contact" => "Caroline Decoster",
        "address1" => "Lieu-dit Le Thibaud",
        "postalCode" => "33330",
        "city" => "Saint-Etienne-de-Lisse",
        "country" => "France",
        "countryIsoCode" => "FR",
        "phoneNumber" => "+33 (0) 5 57 40 14 05",
        "email" => "contact@fleurcardinale.com",
        "website" => "https://fleurcardinale.com"
     ),
    "country" => "France",
    "region" => "Bordeaux",
    "appellation" => "Saint Emilion Grand Cru",
    "grapes" => "Merlot, cabernet-Franc, cabernet-Sauvignon",
    "classification" => "Grand Cru Classé",
    "imageUrl" => "https://assets.ploc.pro/1706/60366380-7887-4642-a6fa-0b66dce37eee.jpg",
    "productUrl" => "https://www.PLOC.co/productUrl",
    "meals" => "Très bel accord avec l 'agneau, de manière générale avec les viandes rouges et le fromage.",
    "volume" => 0.75, // Volume de la bouteille en litre
    "service" => 16, // Température de service
    "degree" => 14,
    "apogee" => array(
        "from"=> 2025,
        "to"=> 2030
    ),
    "unitPrice" => 40, // Prix TTC
    "quantity" => 6
);

$product2 = array (
    "sku" => "W0004",
    "vintage" => 2015,
    "title" => "Champagne Alain Navarre Brut Tradition",
    "color" => "sparkling", // red, rosy, white, sparkling, sweet, other
    "owner" => array(
        "title" => "Château PLOC"
     ),
    "country" => "France",
    "region" => "Champagne",
    "appellation" => "Champagne",
    "grapes" => "Pinot Noir, Pinot Meunier",
    "imageUrl" => "https://assets.ploc.pro/1912/6b290875-2541-4a9e-ae23-37c0d28c258d.jpg",
    "productUrl" => "https://www.PLOC.co/product-1",
    "meals" => "Idéal à l'apéritif",
    "volume" => 1.5, // Volume de la bouteille en litre
    "service" => 9, // Température de service
    "degree" => 12,
    "unitPrice" => 25, // Prix TTC
    "quantity" => 2
);

$products = array($product1, $product2);

$status = $client->sendOrderMessage($plocToken, $text, $purchaseDate, $vendor, $products);
if(!$status) {
    $linkStatus = $client->getLinkStatus($plocToken);
    if($linkStatus == PLOC::LINK_STATUS_CANCELLED) {
        // Le PLOC💙 a delié son compte à partir de l'application
        // UPDATE <customerTable> set PLOC = <NULL> where PLOC <$plocToken>
    }
}
echo "<center>Message " .($status == true ? "" : "non")." envoyé</center>";
```

# Documentation
> Les propriétés (*) sont obligatoires.

```
vendor : informations sur le vendeur, sera repris dans la fiche achat du vin.
    title       : nom du vendeur (*)
    contact     : nom du contact
    address1    : ligne d'adresse 1
    address2    : ligne d'adresse 2
    postalCode  : Code postal
    city        : ville
    phoneNumber : numéro de téléphone (format 03 03 03 03 03)
    email       : email du contact
    website     : site internet

product :
    sku         : votre référence du produit
    vintage     : millésime de la fiche
    title       : nom du vin (*)
    color       : type de vin (*) valeurs possibles -> red, rosy, white, sparkling, sweet, other
                : valeurs possibles 
                    red         : vin rouge
                    rosy        : vin rosé
                    white       : vin blanc
                    sparkling   : effervescent (Champagne & Champagne Rosé, Crémant...)
                    sweet       : vin liquoreux ou doux (Sauternes, Maury)
                    other       : tous les vins n'entrant pas dans une de ces catégories
    country     : pays
    region      : région viticole (le pays est obligatoire si la région est renseignée)
    appellation : L'AOC ou l'IGP du vin (la région est obligatoire si l'appellation est renseignée)
    classification : classement (Cru Bourgeois, 2ème Cru Classé, 1er Cru...)
    imageUrl    : Lien pour télécharger l'image
    productUrl  : Lien vers la fiche produit
    meals       : Ensemble de plats ou de catégorie (PLOC fera automatiquement la conversion)
                  Exemples :
                    Charcuterie, Viande rouge, Gibier, Champignon, Barbecue, Fromage
                    Très bel accord avec l'agneau, de manière générale avec les viandes rouges et le fromage.
                    Apprécié sur des entrées variées, des poissons grillés et des viandes blanches, notamment la volaille.
    grapes      : Assemblage du vin, séparer les cépages par des ","
                   Merlot, Cabernet Franc, Cabernet Sauvignon
                   50% Chardonnay, 50% Pinot Meunier
                   Pour un vin mono cépage ne pas écrire 100% mais juste le nom du cépage :
                    Chardonnay par exemple.
    volume      : volume de la bouteille en litre (0.75, 1.5)
    service     : température de service en degré
    degree      : degrée d'alcool de la fiche
    unitPrice   : prix unitaire TTC de la bouteille
    quantity    : quantité achetée
    apogee      : voir apogee
    owner       : voir owner
    
apogee : période d'apogée
     from   : année de début
     to     : année de fin

owner : informations sur le château ou le domaine, sera repris dans la fiche du vin.
    title           : nom du château ou du domaine (*)
    contact         : nom du contact
    address1        : ligne d'adresse 1
    address2        : ligne d'adresse 2
    postalCode      : Code postal
    city            : ville
    country         : France
    countryIsoCode  : FR
    phoneNumber     : numéro de téléphone (format 03 03 03 03 03)
    email           : email du contact
    website         : site internet
```