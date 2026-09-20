<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class HomeNavigationTest extends TestCase
{
    use RefreshDatabase;

    public function test_home_page_links_to_the_unified_catalog_sections(): void
    {
        $response = $this->get('/');

        $response->assertOk();
        $response->assertSee(route('application').'#ressources-payantes', false);
        $response->assertSee(route('application').'#livres-physiques', false);
        $response->assertSee('Bienvenue sur SENI-CNF EDU.');
        $response->assertDontSee('Ressources sélectionnées');
    }

    public function test_about_page_is_available_from_the_public_site(): void
    {
        $this->get(route('about'))
            ->assertOk()
            ->assertSee('Le parcours derriere SENI-CNF EDU.')
            ->assertSee('Universite Numerique Cheikh Hamidou KANE du Senegal')
            ->assertSee('formation Developpeur web de D-Clic');
    }
}
