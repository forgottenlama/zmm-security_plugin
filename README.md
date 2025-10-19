# ZMM security plugin
Je to plugin určený pre stránku `www.zmm.sk`. Jeho hlavnou úlohou je umožniť vytvárať stránky, na ktoré je umožnený prístup až po prihlásení. Taktiež vytvára prihlasovacie údaje a kontroluje ich. Tento plugin **nie je určený na zabezpečenie citlivých súborov**. Neponúka úplne dokonalé zabezpečenie, pretože je navrhnutý s čo najväčšou jednoduchosťou. Je navrhnutý skôr pre oddelenie verejnej a internej časti stránky. 
---
## Používateľská dokumentácia
*Programátorskú dokumentáciu nájdete v súbore* `DOCUMENTATION.md`

Po nainštalovaní *(je možné pomocou zip súboru)* a aktivovaní *(v správcovi pluginov WordPressu)* sa v ovládacom paneli WordPressu zobrazí záložka `Grid Karty`.
V tejto záložke sa nachádzajú 2 podstránky: `Grid Karty` a `Vygenerované Karty`

### Podstránka `Grid Karty`
Na tejto podstránke sa nachádza iba okno na zadanie kľúča (mena) a tlačidlo `Vygeneruj` ktoré z daného kľúča (mena) vytvorí grid kartu. Po stlačení tlačidla sa po krátkej chvíli zobrazí vyskakovacie okno o informácii či bolo generovanie karty úspešné, alebo informácia o tom aký problém nastal. 

Z daného kľúča sa vygeneruje vždy rovnaká karta, tým pádom je to ochránené pred náhodným pregenerovaním už existujúcej karty. To zároveň spôsobuje že pokiaľ sa zmení človek na danej funkcii, je nutné vygenerovať novú kartu - avšak tá musí byť vygenerovaná pomocou iného kľúča (mena). Kvôli tomu odporúčam vždy v kľúči (mene) uvádzať aj funkciu aj meno danej osoby. *Napr.: MS Rajec Jožko* Poprípade zariadiť dedenie danej Karty. 
 
(každý znak v kľúči je významný, zmenou akéhokoľvek znaku sa vygeneruje úplne iná karta)

### Podstránka `Vygenerované Karty`
Na tejto podstránke sa nachádza tabuľka ktorá zobrazuje vygenerované karty. Iba karty ktoré vidíte v tejto tabuľke sú platné a dajú sa použiť na prihlásenie.

Tabuľka sa skladá z 3 stĺpcov: Kľúč, Grid Karta, Funkcie

#### Stĺpec Kľúč
V tomto stĺpci sa zobrazuje kľúč (meno) danej karty.

#### Stĺpec Grid Karta
V tomto stĺpci sa zobrazuje Grid karta v danom rozpoložení: 6 riadkov a 6 stĺpcov

#### Stĺpec Funkcie
V tomto stĺpci sa nachádzajú 2 tlačidlá: Odstrániť (červené), Exportovať (zelené)

Tlačidlo Odstrániť je určené na odstránenie Karty. Karta sa odstráni z internej databázy, tým pádom sa prestane zobrazovať v tabuľke a nebude sa dať použiť na prihlásenie. Akciu odstránenia je potrebné potvrdiť vo vyskakovacom okne.

Tlačidlo Exportovať je určené na vyexportovanie grid karty do PDF. Po stlačení sa otvorí prázdna nová stránka a po chvíli by sa malo zobraziť okno vášho správcu súborov, kde si nastavíte kam chcete PDF uložiť a následne potvrdíte alebo prerušíte uloženie súboru. 

### Nastavenie stránky na prihlásenie
Na vašej stránke musíte vytvoriť podstránku. Obsahom tejto stránky vo WordPress-e bude iba jediný shortcode: `[grid_auth_verify]`

Adresu tejto podstránky je následne potrebné vložiť do kódu na riadok 424 (funkcia: `protected_page_shortcode`, riadok: `window.location.href = 'https://zmm.sk/prihlasenie';`)

Túto stránku následne nezabudnite zverejniť (publikovať).

### Nastavenie zabezpečených stránok 
Zabezpečená môže byť akákoľvek stránka. Jediné, čo je potrebné je pridať vo WordPress-e do tejto stránky shortcode: `[protected_page]`

### Proces ochrany a prihlasovania
Keď sa užívateľ bude chcieť bez predchádzajúceho prihlásenia dostať na zabezpečenú stránku: Používateľ uvidí prázdnu stránku s hlásením: `Prístup zamietnutý. Presmerovávam na prihlasovaciu stránku.` 

Po krátkom čase sa zobrazí prihlasovací formulár. Formulár obsahuje dva vstupné polia — nad každým je uvedená súradnica konkrétnej bunky. Do príslušných polí vložte hodnoty z týchto buniek na svojej grid karte a stlačte "Overiť". Ak sa zadané hodnoty zhodujú s údajmi niektorej z aktívnych kariet, prihlásenie prebehne úspešne a zobrazí sa potvrdzujúca stránka s kľúčom (menom), pod ktorým ste prihlásený.

Používateľ zostane prihlásený po dobu jednej hodiny. Potom sa musí znova prihlásiť. 