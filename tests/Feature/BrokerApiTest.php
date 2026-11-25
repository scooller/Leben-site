<?php

namespace Tests\Feature;

use App\Models\Broker;
use App\Models\BrokerAlliance;
use App\Models\BrokerBenefit;
use App\Models\BrokerEvent;
use App\Models\BrokerGallery;
use App\Models\BrokerGalleryItem;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BrokerApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_broker_index_returns_category_and_benefits(): void
    {
        $broker = Broker::factory()->create();

        $benefit = BrokerBenefit::factory()->create([
            'title' => 'Contacto semanal KAM',
            'section' => 'Comunicación',
        ]);

        $benefit->categories()->attach($broker->broker_category_id, ['status' => 'included']);

        $response = $this->getJson('/api/v1/brokers');

        $response
            ->assertOk()
            ->assertJsonPath('data.0.id', $broker->id)
            ->assertJsonPath('data.0.category.id', $broker->broker_category_id)
            ->assertJsonPath('data.0.category.benefits.0.title', 'Contacto semanal KAM')
            ->assertJsonPath('data.0.category.benefits.0.status', 'included');
    }

    public function test_broker_related_endpoints_return_active_and_published_records(): void
    {
        $broker = Broker::factory()->create();

        BrokerAlliance::factory()->create([
            'broker_id' => $broker->id,
            'name' => 'Marca Activa',
            'is_active' => true,
        ]);

        BrokerAlliance::factory()->create([
            'broker_id' => $broker->id,
            'name' => 'Marca Inactiva',
            'is_active' => false,
        ]);

        BrokerEvent::factory()->create([
            'broker_id' => $broker->id,
            'title' => 'Evento Publicado',
            'is_published' => true,
        ]);

        BrokerEvent::factory()->create([
            'broker_id' => $broker->id,
            'title' => 'Evento Oculto',
            'is_published' => false,
        ]);

        $gallery = BrokerGallery::factory()->create([
            'broker_id' => $broker->id,
            'title' => 'Galeria Mayo',
            'year' => 2026,
            'month' => 5,
            'is_published' => true,
        ]);

        BrokerGalleryItem::factory()->create([
            'broker_gallery_id' => $gallery->id,
            'caption' => 'Imagen principal',
            'is_active' => true,
        ]);

        BrokerGalleryItem::factory()->create([
            'broker_gallery_id' => $gallery->id,
            'caption' => 'Imagen oculta',
            'is_active' => false,
        ]);

        $this->getJson("/api/v1/brokers/{$broker->id}/alliances")
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.name', 'Marca Activa');

        $this->getJson("/api/v1/brokers/{$broker->id}/events")
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.title', 'Evento Publicado');

        $this->getJson("/api/v1/brokers/{$broker->id}/galleries")
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.title', 'Galeria Mayo');

        $this->getJson("/api/v1/brokers/{$broker->id}/galleries/{$gallery->id}")
            ->assertOk()
            ->assertJsonPath('data.items.0.caption', 'Imagen principal')
            ->assertJsonCount(1, 'data.items');
    }

    public function test_broker_portal_endpoint_returns_aggregated_data(): void
    {
        $broker = Broker::factory()->create(['is_active' => true]);

        BrokerAlliance::factory()->create([
            'broker_id' => $broker->id,
            'name' => 'Alianza Global',
            'is_active' => true,
        ]);

        BrokerEvent::factory()->create([
            'broker_id' => $broker->id,
            'title' => 'Evento Portal',
            'is_published' => true,
        ]);

        $gallery = BrokerGallery::factory()->create([
            'broker_id' => $broker->id,
            'title' => 'Galeria 2025',
            'year' => 2025,
            'month' => 7,
            'is_published' => true,
        ]);

        BrokerGalleryItem::factory()->create([
            'broker_gallery_id' => $gallery->id,
            'caption' => 'Foto 1',
            'is_active' => true,
        ]);

        $response = $this->getJson('/api/v1/brokers/portal');

        $response
            ->assertOk()
            ->assertJsonPath('data.broker.id', $broker->id)
            ->assertJsonPath('data.alliances.0.name', 'Alianza Global')
            ->assertJsonPath('data.events.0.title', 'Evento Portal')
            ->assertJsonPath('data.galleries.0.title', 'Galeria 2025')
            ->assertJsonPath('data.galleries.0.items.0.caption', 'Foto 1');
    }
}
