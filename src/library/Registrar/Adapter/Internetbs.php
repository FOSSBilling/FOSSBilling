<?php

use Symfony\Contracts\HttpClient\Exception\HttpExceptionInterface;

class Registrar_Adapter_Internetbs extends Registrar_Adapter_InternetbsBase
{
    protected function getBrandName(): string
    {
        return 'Internetbs';
    }

    protected function getApiBaseUrl(): string
    {
        return 'https://api.internet.bs';
    }

    protected function getTestApiBaseUrl(): string
    {
        return 'https://testapi.internet.bs';
    }

    public static function getConfig()
    {
        return [
            'label' => 'Manages domains on Internetbs via API',
            'form' => [
                'apikey' => ['text', [
                    'label' => 'Internetbs API key',
                    'description' => 'Internetbs API key',
                ],
                ],
                'password' => ['password', [
                    'label' => 'Internetbs API password',
                    'description' => 'Internetbs API password',
                    'renderPassword' => true,
                ],
                ],
            ],
        ];
    }

    public function getTlds()
    {
        return [
            '.abogado', '.ac', '.academy', '.accountants', '.actor', '.adult', '.aero', '.af', '.ag', '.agency',
            '.ai', '.airforce', '.am', '.apartments', '.app', '.archi', '.army', '.art', '.asia', '.associates',
            '.at', '.attorney', '.au', '.auction', '.audio', '.auto', '.autos', '.baby', '.band', '.bar',
            '.bargains', '.be', '.beer', '.berlin', '.best', '.bet', '.bid', '.bike', '.bingo', '.bio',
            '.biz', '.black', '.blackfriday', '.blog', '.blue', '.boats', '.boutique', '.build', '.builders', '.business',
            '.buzz', '.bz', '.ca', '.cab', '.cafe', '.cam', '.camera', '.camp', '.capetown', '.capital',
            '.car', '.cards', '.care', '.career', '.careers', '.cars', '.casa', '.cash', '.casino', '.catering',
            '.cc', '.center', '.ceo', '.ch', '.charity', '.chat', '.cheap', '.church', '.city', '.claims',
            '.cleaning', '.click', '.clinic', '.clothing', '.cloud', '.club', '.cn', '.co', '.coach', '.codes',
            '.coffee', '.college', '.cologne', '.com', '.community', '.company', '.computer', '.condos', '.construction', '.consulting',
            '.contractors', '.cooking', '.cool', '.country', '.coupons', '.courses', '.credit', '.creditcard', '.cricket', '.cruises',
            '.cx', '.cymru', '.cz', '.dance', '.date', '.dating', '.de', '.deals', '.degree', '.delivery',
            '.democrat', '.dental', '.dentist', '.design', '.diamonds', '.digital', '.direct', '.directory', '.discount', '.dk',
            '.doctor', '.dog', '.domains', '.download', '.durban', '.earth', '.ec', '.education', '.email', '.energy',
            '.engineer', '.engineering', '.enterprises', '.equipment', '.es', '.estate', '.eu', '.events', '.exchange', '.expert',
            '.exposed', '.express', '.fail', '.faith', '.family', '.fan', '.fans', '.farm', '.fashion', '.feedback',
            '.fi', '.finance', '.financial', '.fish', '.fishing', '.fit', '.fitness', '.flights', '.florist', '.football',
            '.forex', '.forsale', '.foundation', '.fr', '.fun', '.fund', '.furniture', '.futbol', '.fyi', '.gallery',
            '.game', '.games', '.garden', '.gay', '.gd', '.gift', '.gifts', '.gives', '.glass', '.global',
            '.gmbh', '.gold', '.golf', '.graphics', '.gratis', '.green', '.gripe', '.group', '.gs', '.guide',
            '.guitars', '.guru', '.gy', '.hair', '.hamburg', '.health', '.healthcare', '.help', '.hiphop', '.hiv',
            '.hockey', '.holdings', '.holiday', '.homes', '.horse', '.hospital', '.host', '.hosting', '.house', '.how',
            '.ht', '.hu', '.immo', '.immobilien', '.in', '.inc', '.industries', '.info', '.ink', '.institute',
            '.insurance', '.insure', '.international', '.investments', '.io', '.irish', '.is', '.it', '.jetzt', '.jewelry',
            '.jobs', '.joburg', '.jp', '.kaufen', '.kim', '.kitchen', '.kiwi', '.kr', '.la', '.land',
            '.law', '.lawyer', '.lc', '.lease', '.legal', '.lgbt', '.li', '.life', '.lighting', '.limited',
            '.limo', '.link', '.live', '.llc', '.loan', '.loans', '.lol', '.london', '.love', '.lt',
            '.ltd', '.lu', '.luxury', '.lv', '.maison', '.makeup', '.management', '.market', '.marketing', '.mba',
            '.me', '.media', '.memorial', '.men', '.menu', '.mg', '.miami', '.mobi', '.moda', '.money',
            '.monster', '.mortgage', '.movie', '.ms', '.mu', '.music', '.mx', '.name', '.navy', '.net',
            '.network', '.news', '.nf', '.ninja', '.nl', '.no', '.nyc', '.nz', '.one', '.online',
            '.org', '.organic', '.paris', '.partners', '.parts', '.party', '.pet', '.ph', '.photography', '.photos',
            '.pics', '.pictures', '.pink', '.pizza', '.pl', '.place', '.plumbing', '.plus', '.poker', '.porn',
            '.press', '.pro', '.productions', '.promo', '.properties', '.property', '.protection', '.pt', '.pub', '.pw',
            '.qpon', '.quebec', '.quest', '.racing', '.radio', '.re', '.realestate', '.recipes', '.red', '.rehab',
            '.reise', '.reisen', '.rent', '.rentals', '.repair', '.report', '.republican', '.rest', '.restaurant', '.review',
            '.reviews', '.rich', '.rip', '.ro', '.rocks', '.rodeo', '.ru', '.run', '.sale', '.salon',
            '.sarl', '.sc', '.school', '.science', '.scot', '.se', '.security', '.services', '.sex', '.sexy',
            '.sh', '.shiksha', '.shoes', '.shop', '.shopping', '.show', '.singles', '.site', '.ski', '.skin',
            '.so', '.soccer', '.social', '.software', '.solar', '.solutions', '.space', '.sport', '.st', '.storage',
            '.store', '.stream', '.studio', '.study', '.style', '.sucks', '.supplies', '.supply', '.support', '.surf',
            '.surgery', '.sx', '.systems', '.tattoo', '.tax', '.taxi', '.tc', '.team', '.tech', '.technology',
            '.tel', '.tennis', '.theater', '.theatre', '.tickets', '.tips', '.tires', '.tk', '.tl', '.tm',
            '.to', '.today', '.tools', '.top', '.tours', '.town', '.toys', '.trade', '.trading', '.training',
            '.travel', '.tube', '.tv', '.tw', '.uk', '.university', '.uno', '.us', '.uy', '.vc',
            '.vegas', '.ventures', '.vet', '.vg', '.viajes', '.video', '.villas', '.vision', '.vodka', '.vote',
            '.voting', '.voyage', '.wales', '.watch', '.webcam', '.website', '.wedding', '.wf', '.wien', '.wiki',
            '.win', '.wine', '.work', '.works', '.world', '.ws', '.wtf', '.xxx', '.xyz', '.yoga',
            '.zone',
        ];
    }
}