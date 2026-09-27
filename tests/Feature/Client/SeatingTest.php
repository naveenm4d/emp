<?php

use App\Domains\Client\Models\Client;
use App\Domains\Event\Models\Event;
use App\Domains\Guest\Enums\ApprovalStatus;
use App\Domains\Guest\Models\Guest;
use App\Domains\Rsvp\Models\Rsvp;
use App\Domains\Seating\Models\EventTable;
use App\Domains\Seating\Models\SeatAssignment;
use App\Domains\Seating\Models\VenueElement;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    $this->client = Client::factory()->create();
    $this->event = Event::factory()->for($this->client)->create();
    $this->actingAs($this->client, 'client');
});

/**
 * Where the guest's party sits: seat numbers by party member.
 *
 * @return list<int>
 */
function seatsOf(Guest $guest): array
{
    return SeatAssignment::where('guest_id', $guest->id)->orderBy('party_member')->pluck('seat_number')->all();
}

it('adds, renames and resizes tables', function () {
    $this->post("/app/events/{$this->event->id}/tables", ['name' => 'A', 'seat_count' => 8, 'shape' => 'round'])
        ->assertSessionHas('success', 'A added.');

    $table = EventTable::sole();
    expect($table)->name->toBe('A')->seat_count->toBe(8);

    $this->patch("/app/tables/{$table->id}", ['name' => 'Head table', 'seat_count' => 10, 'shape' => 'round'])->assertSessionHas('success');
    expect($table->fresh())->name->toBe('Head table')->seat_count->toBe(10);
});

it('draws tables in the chosen shape', function () {
    $this->post("/app/events/{$this->event->id}/tables", ['name' => 'Head', 'seat_count' => 10, 'shape' => 'rectangle'])
        ->assertSessionHas('success');

    $table = EventTable::sole();
    expect($table->shape->value)->toBe('rectangle');

    $this->patch("/app/tables/{$table->id}", ['name' => 'Head', 'seat_count' => 10, 'shape' => 'oval'])->assertSessionHas('success');
    expect($table->fresh()->shape->value)->toBe('oval');

    $this->get("/app/events/{$this->event->id}/seating")
        ->assertInertia(fn (Assert $page) => $page->where('tables.0.shape', 'oval')->has('tableShapes', 4));
});

it('refuses unknown table shapes', function () {
    $this->post("/app/events/{$this->event->id}/tables", ['name' => 'A', 'seat_count' => 4, 'shape' => 'hexagon'])
        ->assertSessionHasErrors(['shape' => 'The selected shape is invalid.']);
});

it('validates tables', function () {
    EventTable::factory()->for($this->event)->create(['name' => 'A']);

    $this->post("/app/events/{$this->event->id}/tables", ['name' => '', 'seat_count' => 51, 'shape' => 'round'])
        ->assertSessionHasErrors(['name' => 'The name field is required.', 'seat_count' => 'The seat count field must not be greater than 50.']);

    $this->post("/app/events/{$this->event->id}/tables", ['name' => 'a', 'seat_count' => 4, 'shape' => 'round'])
        ->assertSessionHas('error', 'This event already has a table with that name.');

    expect(EventTable::count())->toBe(1);
});

it('refuses removing seats someone sits in', function () {
    $table = EventTable::factory()->for($this->event)->seats(8)->create();
    $guest = Guest::factory()->for($this->event)->create();
    $this->post("/app/tables/{$table->id}/seats", ['guest_id' => $guest->id, 'seat_number' => 6]);

    $this->patch("/app/tables/{$table->id}", ['name' => $table->name, 'seat_count' => 5, 'shape' => 'round'])
        ->assertSessionHas('error', 'Guests sit in the seats you want to remove. Move them first.');

    expect($table->fresh()->seat_count)->toBe(8);
});

it('seats a whole party together, going round the table from the chosen seat', function () {
    $table = EventTable::factory()->for($this->event)->seats(4)->create(['name' => 'A']);
    $guest = Guest::factory()->for($this->event)->create(['name' => 'John', 'additional_guests' => 1, 'children' => 1]);

    $this->post("/app/tables/{$table->id}/seats", ['guest_id' => $guest->id, 'seat_number' => 3])
        ->assertSessionHas('success', 'John seated at A.');

    expect(seatsOf($guest))->toBe([3, 4, 1]);
});

it('refuses a party bigger than the free seats', function () {
    $table = EventTable::factory()->for($this->event)->seats(2)->create(['name' => 'B']);
    $guest = Guest::factory()->for($this->event)->create(['name' => 'John', 'additional_guests' => 2]);

    $this->post("/app/tables/{$table->id}/seats", ['guest_id' => $guest->id, 'seat_number' => 1])
        ->assertSessionHas('error', "B has 2 free seats; John's party needs 3.");

    expect(SeatAssignment::count())->toBe(0);
});

it('moves a seated guest instead of seating them twice', function () {
    [$first, $second] = EventTable::factory()->for($this->event)->count(2)->create();
    $guest = Guest::factory()->for($this->event)->create(['additional_guests' => 1]);

    $this->post("/app/tables/{$first->id}/seats", ['guest_id' => $guest->id, 'seat_number' => 1]);
    $this->post("/app/tables/{$second->id}/seats", ['guest_id' => $guest->id, 'seat_number' => 5])->assertSessionHas('success');

    expect(SeatAssignment::where('guest_id', $guest->id)->pluck('table_id')->unique()->all())->toBe([$second->id])
        ->and(seatsOf($guest))->toBe([5, 6]);
});

it('refuses a seat someone already sits in', function () {
    $table = EventTable::factory()->for($this->event)->create();
    [$ada, $bob] = Guest::factory()->for($this->event)->count(2)->create();
    $this->post("/app/tables/{$table->id}/seats", ['guest_id' => $ada->id, 'seat_number' => 1]);

    $this->post("/app/tables/{$table->id}/seats", ['guest_id' => $bob->id, 'seat_number' => 1])
        ->assertSessionHas('error', 'Someone already sits in this seat.');
});

it('only seats approved guests who have not declined', function (array $state) {
    $table = EventTable::factory()->for($this->event)->create();
    $guest = Guest::factory()->for($this->event)->create($state);

    $this->post("/app/tables/{$table->id}/seats", ['guest_id' => $guest->id, 'seat_number' => 1])
        ->assertSessionHas('error', "Only approved guests who haven't declined can be seated.");
})->with([
    'declined' => [['rsvp_status' => 'declined']],
    'waiting for approval' => [['approval_status' => ApprovalStatus::Pending]],
]);

it('refuses guests of another event', function () {
    $table = EventTable::factory()->for($this->event)->create();
    $stranger = Guest::factory()->create();

    $this->post("/app/tables/{$table->id}/seats", ['guest_id' => $stranger->id, 'seat_number' => 1])
        ->assertSessionHasErrors(['guest_id' => 'Pick a guest of this event.']);
});

it('swaps two parties between tables', function () {
    $a = EventTable::factory()->for($this->event)->seats(4)->create();
    $b = EventTable::factory()->for($this->event)->seats(4)->create();
    $john = Guest::factory()->for($this->event)->create(['additional_guests' => 1]);
    $mary = Guest::factory()->for($this->event)->create();
    $this->post("/app/tables/{$a->id}/seats", ['guest_id' => $john->id, 'seat_number' => 1]);
    $this->post("/app/tables/{$b->id}/seats", ['guest_id' => $mary->id, 'seat_number' => 3]);

    $this->post("/app/events/{$this->event->id}/seating/swap", ['guest_id' => $john->id, 'other_guest_id' => $mary->id])
        ->assertSessionHas('success', 'Seats swapped.');

    expect(SeatAssignment::where('guest_id', $john->id)->pluck('table_id')->unique()->all())->toBe([$b->id])
        ->and(seatsOf($john))->toBe([3, 4])
        ->and(seatsOf($mary))->toBe([1]);
});

it('keeps both parties where they were when a swap does not fit', function () {
    $a = EventTable::factory()->for($this->event)->seats(4)->create();
    $b = EventTable::factory()->for($this->event)->seats(1)->create(['name' => 'Small']);
    $john = Guest::factory()->for($this->event)->create(['name' => 'John', 'additional_guests' => 1]);
    $mary = Guest::factory()->for($this->event)->create();
    $this->post("/app/tables/{$a->id}/seats", ['guest_id' => $john->id, 'seat_number' => 1]);
    $this->post("/app/tables/{$b->id}/seats", ['guest_id' => $mary->id, 'seat_number' => 1]);

    $this->post("/app/events/{$this->event->id}/seating/swap", ['guest_id' => $john->id, 'other_guest_id' => $mary->id])
        ->assertSessionHas('error', "Small has 1 free seats; John's party needs 2.");

    expect(seatsOf($john))->toBe([1, 2])
        ->and(SeatAssignment::where('guest_id', $mary->id)->value('table_id'))->toBe($b->id);
});

it('replaces a seated guest with another', function () {
    $table = EventTable::factory()->for($this->event)->create();
    $john = Guest::factory()->for($this->event)->create();
    $mary = Guest::factory()->for($this->event)->create();
    $this->post("/app/tables/{$table->id}/seats", ['guest_id' => $john->id, 'seat_number' => 2]);

    $this->post("/app/events/{$this->event->id}/seating/replace", ['guest_id' => $john->id, 'other_guest_id' => $mary->id])
        ->assertSessionHas('success');

    expect(seatsOf($john))->toBe([])->and(seatsOf($mary))->toBe([2]);
});

it('frees a party\'s seats', function () {
    $table = EventTable::factory()->for($this->event)->create();
    $guest = Guest::factory()->for($this->event)->create(['children' => 2]);
    $this->post("/app/tables/{$table->id}/seats", ['guest_id' => $guest->id, 'seat_number' => 1]);

    $this->delete("/app/guests/{$guest->id}/seats")->assertSessionHas('success');

    expect(SeatAssignment::count())->toBe(0);
});

it('keeps a declined guest\'s seats and shows them as declined', function () {
    $table = EventTable::factory()->for($this->event)->create();
    $guest = Guest::factory()->for($this->event)->create();
    $rsvp = Rsvp::factory()->for($guest)->for($this->event)->sent()->create();
    $this->post("/app/tables/{$table->id}/seats", ['guest_id' => $guest->id, 'seat_number' => 1]);
    $this->event->update(['state' => 'published']);

    $this->post("/{$this->event->slug}/{$guest->link->code}/respond", ['attendance' => 'declined'])->assertSessionHas('success');

    expect(seatsOf($guest))->toBe([1]);
    $this->get("/app/events/{$this->event->id}/seating")
        ->assertInertia(fn (Assert $page) => $page->where('tables.0.seats.0.status', 'declined'));
});

it('follows a party that shrinks or grows after the RSVP', function () {
    $this->event->update([
        'state' => 'published',
        'event_registration_settings' => ['party' => ['plus_ones' => true, 'max_additional_guests' => 3]],
    ]);
    $table = EventTable::factory()->for($this->event)->seats(4)->create();
    $guest = Guest::factory()->for($this->event)->create(['invited_additional_guests' => 2, 'additional_guests' => 2]);
    Rsvp::factory()->for($guest)->for($this->event)->sent()->create();
    $this->post("/app/tables/{$table->id}/seats", ['guest_id' => $guest->id, 'seat_number' => 1]);
    $link = "/{$this->event->slug}/{$guest->link->code}";

    $this->post("{$link}/respond", ['attendance' => 'accepted', 'additional_guests' => 0])->assertSessionHas('success');
    expect(seatsOf($guest))->toBe([1]);

    $guest->fresh()->rsvps()->first()->update(['status' => 'sent', 'responded_at' => null]);
    $this->post("{$link}/respond", ['attendance' => 'accepted', 'additional_guests' => 2])->assertSessionHas('success');
    expect(seatsOf($guest))->toBe([1, 2, 3]);
});

it('lists confirmed guests without seats and auto-seats them', function () {
    EventTable::factory()->for($this->event)->seats(3)->create(['sort_order' => 0]);
    $family = Guest::factory()->for($this->event)->create(['rsvp_status' => 'confirmed', 'additional_guests' => 1, 'children' => 1]);
    $single = Guest::factory()->for($this->event)->create(['rsvp_status' => 'confirmed']);
    Guest::factory()->for($this->event)->create(['rsvp_status' => 'pending']);

    $this->get("/app/events/{$this->event->id}/seating")
        ->assertInertia(fn (Assert $page) => $page
            ->component('client/events/seating')
            ->where('summary.confirmed_people', 4)
            ->where('summary.confirmed_seated', 0)
            ->has('summary.unseated', 2));

    $this->post("/app/events/{$this->event->id}/seating/auto")
        ->assertSessionHas('success', "Seated 1 guest. 1 didn't fit: add a table or seats.");

    expect(seatsOf($family))->toBe([1, 2, 3])->and(seatsOf($single))->toBe([]);
});

it('frees the seats of a removed table', function () {
    $table = EventTable::factory()->for($this->event)->create();
    $guest = Guest::factory()->for($this->event)->create();
    $this->post("/app/tables/{$table->id}/seats", ['guest_id' => $guest->id, 'seat_number' => 1]);

    $this->delete("/app/tables/{$table->id}")->assertSessionHas('success');

    expect(EventTable::count())->toBe(0)->and(SeatAssignment::count())->toBe(0);
});

it('forbids another client\'s seating', function () {
    $event = Event::factory()->create();
    $table = EventTable::factory()->for($event)->create();

    $this->get("/app/events/{$event->id}/seating")->assertForbidden();
    $this->post("/app/events/{$event->id}/tables", ['name' => 'X', 'seat_count' => 2, 'shape' => 'round'])->assertForbidden();
    $this->patch("/app/tables/{$table->id}", ['name' => 'X', 'seat_count' => 2, 'shape' => 'round'])->assertForbidden();
    $this->delete("/app/tables/{$table->id}")->assertForbidden();
});

it('splits a party that does not fit, leaving the rest waiting for seats', function () {
    $table = EventTable::factory()->for($this->event)->seats(2)->create(['name' => 'A']);
    $john = Guest::factory()->for($this->event)->create(['name' => 'John', 'rsvp_status' => 'confirmed', 'additional_guests' => 1, 'children' => 2]);

    $this->post("/app/tables/{$table->id}/seats", ['guest_id' => $john->id, 'seat_number' => 1, 'mode' => 'split'])
        ->assertSessionHas('success', "2 of John's party seated at A; 2 still need seats.");

    expect(SeatAssignment::where('guest_id', $john->id)->orderBy('party_member')->pluck('party_member')->all())->toBe([0, 1]);

    $this->get("/app/events/{$this->event->id}/seating")
        ->assertInertia(fn (Assert $page) => $page
            ->where('summary.unseated.0.missing', 2)
            ->where('guests.0.missing_members', [
                ['party_member' => 2, 'label' => "John's child 1"],
                ['party_member' => 3, 'label' => "John's child 2"],
            ])
            ->where('guests.0.seats.1.table_name', 'A'));
});

it('seats the rest of a split party at another table, keeping the others', function () {
    $a = EventTable::factory()->for($this->event)->seats(2)->create();
    $b = EventTable::factory()->for($this->event)->seats(4)->create(['name' => 'B']);
    $john = Guest::factory()->for($this->event)->create(['name' => 'John', 'additional_guests' => 1, 'children' => 2]);
    $this->post("/app/tables/{$a->id}/seats", ['guest_id' => $john->id, 'seat_number' => 1, 'mode' => 'split']);

    $this->post("/app/tables/{$b->id}/seats", ['guest_id' => $john->id, 'seat_number' => 3, 'mode' => 'rest'])
        ->assertSessionHas('success', "John's party is seated.");

    expect(SeatAssignment::where('guest_id', $john->id)->orderBy('party_member')->get(['party_member', 'table_id', 'seat_number'])->toArray())->toBe([
        ['party_member' => 0, 'table_id' => $a->id, 'seat_number' => 1],
        ['party_member' => 1, 'table_id' => $a->id, 'seat_number' => 2],
        ['party_member' => 2, 'table_id' => $b->id, 'seat_number' => 3],
        ['party_member' => 3, 'table_id' => $b->id, 'seat_number' => 4],
    ]);
});

it('moves one person of a party to another table', function () {
    $a = EventTable::factory()->for($this->event)->seats(4)->create();
    $b = EventTable::factory()->for($this->event)->seats(4)->create(['name' => 'B']);
    $john = Guest::factory()->for($this->event)->create(['name' => 'John', 'children' => 2]);
    $this->post("/app/tables/{$a->id}/seats", ['guest_id' => $john->id, 'seat_number' => 1]);

    $this->post("/app/tables/{$b->id}/seats", ['guest_id' => $john->id, 'seat_number' => 2, 'mode' => 'member', 'party_member' => 2])
        ->assertSessionHas('success', "John's child 2 seated at B.");

    expect(SeatAssignment::where('guest_id', $john->id)->orderBy('party_member')->get(['table_id', 'seat_number'])->toArray())->toBe([
        ['table_id' => $a->id, 'seat_number' => 1],
        ['table_id' => $a->id, 'seat_number' => 2],
        ['table_id' => $b->id, 'seat_number' => 2],
    ]);
});

it('refuses moving a person into a taken seat or who is not in the party', function () {
    $table = EventTable::factory()->for($this->event)->seats(4)->create();
    $john = Guest::factory()->for($this->event)->create(['additional_guests' => 1]);
    $this->post("/app/tables/{$table->id}/seats", ['guest_id' => $john->id, 'seat_number' => 1]);

    $this->post("/app/tables/{$table->id}/seats", ['guest_id' => $john->id, 'seat_number' => 1, 'mode' => 'member', 'party_member' => 1])
        ->assertSessionHas('error', 'Someone already sits in this seat.');
    $this->post("/app/tables/{$table->id}/seats", ['guest_id' => $john->id, 'seat_number' => 3, 'mode' => 'member', 'party_member' => 2])
        ->assertSessionHas('error', "That person isn't in the party.");
    $this->post("/app/tables/{$table->id}/seats", ['guest_id' => $john->id, 'seat_number' => 3, 'mode' => 'member'])
        ->assertSessionHasErrors('party_member');

    expect(seatsOf($john))->toBe([1, 2]);
});

it('frees one person of a party', function () {
    $table = EventTable::factory()->for($this->event)->create();
    $john = Guest::factory()->for($this->event)->create(['name' => 'John', 'children' => 2]);
    $this->post("/app/tables/{$table->id}/seats", ['guest_id' => $john->id, 'seat_number' => 1]);

    $this->delete("/app/guests/{$john->id}/seats?party_member=2")
        ->assertSessionHas('success', "John's child 2's seat is free.");

    expect(SeatAssignment::where('guest_id', $john->id)->orderBy('party_member')->pluck('party_member')->all())->toBe([0, 1]);
});

it('saves where tables and venue elements stand on the floor plan', function () {
    $table = EventTable::factory()->for($this->event)->create();
    $stage = VenueElement::factory()->for($this->event)->create();
    $otherTable = EventTable::factory()->create(['pos_x' => 10, 'pos_y' => 10]);

    $this->patch("/app/events/{$this->event->id}/seating/layout", [
        'tables' => [
            ['id' => $table->id, 'x' => 200, 'y' => 340],
            ['id' => $otherTable->id, 'x' => 900, 'y' => 900],
        ],
        'elements' => [['id' => $stage->id, 'x' => 500, 'y' => 20, 'width' => 400, 'height' => 120]],
    ])->assertRedirect()->assertSessionHasNoErrors();

    expect($table->fresh())->pos_x->toBe(200)->pos_y->toBe(340)
        ->and($stage->fresh())->pos_x->toBe(500)->width->toBe(400)->height->toBe(120)
        ->and($otherTable->fresh())->pos_x->toBe(10);
});

it('keeps floor plan positions on the canvas', function () {
    $table = EventTable::factory()->for($this->event)->create();

    $this->patch("/app/events/{$this->event->id}/seating/layout", [
        'tables' => [['id' => $table->id, 'x' => 5000, 'y' => -1]],
        'elements' => [],
    ])->assertSessionHasErrors(['tables.0.x', 'tables.0.y']);
});

it('adds, renames and removes venue elements', function () {
    $this->post("/app/events/{$this->event->id}/venue-elements", ['kind' => 'poruwa', 'x' => 700, 'y' => 60, 'width' => 180, 'height' => 180])
        ->assertSessionHas('success', 'Poruwa added.');

    $element = VenueElement::sole();
    expect($element->label)->toBeNull();

    $this->patch("/app/venue-elements/{$element->id}", ['kind' => 'custom', 'label' => 'Gift table', 'x' => 700, 'y' => 60, 'width' => 180, 'height' => 120])
        ->assertSessionHas('success', 'Gift table saved.');

    $this->delete("/app/venue-elements/{$element->id}")->assertSessionHas('success', 'Gift table removed.');
    expect(VenueElement::count())->toBe(0);
});

it('needs a name for a custom venue element', function () {
    $this->post("/app/events/{$this->event->id}/venue-elements", ['kind' => 'custom', 'x' => 0, 'y' => 0, 'width' => 180, 'height' => 120])
        ->assertSessionHasErrors(['label' => 'Give the custom element a name.']);
});

it('keeps other clients off an event\'s venue elements', function () {
    $element = VenueElement::factory()->create();

    $this->delete("/app/venue-elements/{$element->id}")->assertForbidden();
    $this->post("/app/events/{$element->event_id}/venue-elements", ['kind' => 'bar', 'x' => 0, 'y' => 0, 'width' => 220, 'height' => 90])->assertForbidden();
});

it('shows the floor plan on the seating page', function () {
    EventTable::factory()->for($this->event)->create(['pos_x' => 120, 'pos_y' => 80]);
    VenueElement::factory()->for($this->event)->create();

    $this->get("/app/events/{$this->event->id}/seating")->assertInertia(fn (Assert $page) => $page
        ->where('tables.0.x', 120)
        ->where('tables.0.y', 80)
        ->where('venueElements.0.kind', 'stage')
        ->where('venueElements.0.display_label', 'Stage')
        ->has('venueElementKinds', 12));
});

it('refuses floor plan changes on a cancelled event', function () {
    $event = Event::factory()->for($this->client)->cancelled()->create();
    $table = EventTable::factory()->for($event)->create();

    $this->from('/app')->patch("/app/events/{$event->id}/seating/layout", [
        'tables' => [['id' => $table->id, 'x' => 100, 'y' => 100]],
        'elements' => [],
    ])->assertSessionHas('error');

    expect($table->fresh()->pos_x)->toBeNull();
});
