<?php

namespace Database\Seeders;

use App\Models\City;
use App\Models\Place;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class PlaceSeeder extends Seeder
{
    public function run(): void
    {
        $spCity = City::where('slug', 'sao-paulo')->first();
        $rjCity = City::where('slug', 'rio-de-janeiro')->first();

        $places = [
            // São Paulo
            [
                'city_id' => $spCity->id,
                'name' => 'Parque Ibirapuera',
                'address' => 'Av. Pedro Álvares Cabral, s/n - Vila Mariana',
                'neighborhood' => 'Vila Mariana',
                'lat' => -23.5874,
                'lng' => -46.6576,
            ],
            [
                'city_id' => $spCity->id,
                'name' => 'MASP',
                'address' => 'Av. Paulista, 1578 - Bela Vista',
                'neighborhood' => 'Bela Vista',
                'lat' => -23.5614,
                'lng' => -46.6560,
            ],
            [
                'city_id' => $spCity->id,
                'name' => 'Pinacoteca',
                'address' => 'Praça da Luz, 2 - Luz',
                'neighborhood' => 'Luz',
                'lat' => -23.5342,
                'lng' => -46.6339,
            ],
            [
                'city_id' => $spCity->id,
                'name' => 'Zoológico de São Paulo',
                'address' => 'Av. Miguel Stéfano, 4241 - Água Funda',
                'neighborhood' => 'Água Funda',
                'lat' => -23.6505,
                'lng' => -46.6200,
            ],
            [
                'city_id' => $spCity->id,
                'name' => 'Aquário de São Paulo',
                'address' => 'Rua Huet Bacelar, 407 - Ipiranga',
                'neighborhood' => 'Ipiranga',
                'lat' => -23.5935,
                'lng' => -46.6125,
            ],
            [
                'city_id' => $spCity->id,
                'name' => 'Parque Villa-Lobos',
                'address' => 'Av. Prof. Fonseca Rodrigues, 2001 - Alto de Pinheiros',
                'neighborhood' => 'Alto de Pinheiros',
                'lat' => -23.5468,
                'lng' => -46.7222,
            ],
            [
                'city_id' => $spCity->id,
                'name' => 'Museu do Futebol',
                'address' => 'Praça Charles Miller, s/n - Pacaembu',
                'neighborhood' => 'Pacaembu',
                'lat' => -23.5438,
                'lng' => -46.6652,
            ],
            [
                'city_id' => $spCity->id,
                'name' => 'KidZania',
                'address' => 'Rua Palestra Itália, 500 - Perdizes',
                'neighborhood' => 'Perdizes',
                'lat' => -23.5280,
                'lng' => -46.6800,
            ],
            [
                'city_id' => $spCity->id,
                'name' => 'Parque da Mônica',
                'address' => 'Av. das Nações Unidas, 22540 - Jurubatuba',
                'neighborhood' => 'Jurubatuba',
                'lat' => -23.6234,
                'lng' => -46.7012,
            ],
            [
                'city_id' => $spCity->id,
                'name' => 'A Casa do Porco',
                'address' => 'Rua Araújo, 124 - República',
                'neighborhood' => 'República',
                'lat' => -23.5420,
                'lng' => -46.6450,
            ],
            // Rio de Janeiro
            [
                'city_id' => $rjCity->id,
                'name' => 'Praia de Copacabana',
                'address' => 'Av. Atlântica - Copacabana',
                'neighborhood' => 'Copacabana',
                'lat' => -22.9711,
                'lng' => -43.1822,
            ],
            [
                'city_id' => $rjCity->id,
                'name' => 'Cristo Redentor',
                'address' => 'Parque Nacional da Tijuca - Alto da Boa Vista',
                'neighborhood' => 'Alto da Boa Vista',
                'lat' => -22.9519,
                'lng' => -43.2105,
            ],
            [
                'city_id' => $rjCity->id,
                'name' => 'Pão de Açúcar',
                'address' => 'Av. Pasteur, 520 - Urca',
                'neighborhood' => 'Urca',
                'lat' => -22.9492,
                'lng' => -43.1545,
            ],
            [
                'city_id' => $rjCity->id,
                'name' => 'AquaRio',
                'address' => 'Via Binário do Porto, s/n - Gamboa',
                'neighborhood' => 'Gamboa',
                'lat' => -22.8936,
                'lng' => -43.1866,
            ],
            [
                'city_id' => $rjCity->id,
                'name' => 'Jardim Botânico',
                'address' => 'Rua Jardim Botânico, 1008 - Jardim Botânico',
                'neighborhood' => 'Jardim Botânico',
                'lat' => -22.9682,
                'lng' => -43.2252,
            ],
            [
                'city_id' => $rjCity->id,
                'name' => 'Museu do Amanhã',
                'address' => 'Praça Mauá, 1 - Centro',
                'neighborhood' => 'Centro',
                'lat' => -22.8944,
                'lng' => -43.1797,
            ],
            [
                'city_id' => $rjCity->id,
                'name' => 'Parque Lage',
                'address' => 'Rua Jardim Botânico, 414 - Jardim Botânico',
                'neighborhood' => 'Jardim Botânico',
                'lat' => -22.9606,
                'lng' => -43.2111,
            ],
            [
                'city_id' => $rjCity->id,
                'name' => 'Maracanã',
                'address' => 'Av. Pres. Castelo Branco, Portão 3 - Maracanã',
                'neighborhood' => 'Maracanã',
                'lat' => -22.9122,
                'lng' => -43.2302,
            ],
            [
                'city_id' => $rjCity->id,
                'name' => 'Confeitaria Colombo',
                'address' => 'Rua Gonçalves Dias, 32 - Centro',
                'neighborhood' => 'Centro',
                'lat' => -22.9039,
                'lng' => -43.1765,
            ],
            [
                'city_id' => $rjCity->id,
                'name' => 'Pedra da Gávea',
                'address' => 'Estrada da Gávea - São Conrado',
                'neighborhood' => 'São Conrado',
                'lat' => -22.9978,
                'lng' => -43.2846,
            ],
        ];

        foreach ($places as $place) {
            Place::create([
                'id' => Str::uuid(),
                ...$place,
            ]);
        }
    }
}
