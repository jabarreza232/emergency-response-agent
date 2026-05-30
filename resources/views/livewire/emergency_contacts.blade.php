<?php

use Livewire\Volt\Component;
use App\Models\EmergencyLog;
use App\Models\EmergencyContact;
use Livewire\WithPagination;
use Livewire\Attributes\Layout;

new #[Layout('layouts.app')] class extends Component
{
    use WithPagination;

    public $showContactForm = false;
    public $editingContact = null;
    protected $rules = [
        'contact_name' => 'required|string|max:255',
        'contact_phone' => 'required|string|max:20',
        'contact_relationship' => 'nullable|string|max:100',
        'contact_email' => 'nullable|email|max:255',
        'contact_priority' => 'required|integer|min:1|max:10',
    ];
    // Contact form fields
    public $contact_name = '';
    public $contact_phone = '';
    public $contact_relationship = '';
    public $contact_email = '';
    public $contact_priority = 1;


    public function with(): array
    {
        return [
            'contacts' => EmergencyContact::where('user_id', auth()->id())
                ->orderBy('priority', 'asc')
                ->get(),

        ];
    }
    /**
     * Add new emergency contact.
     */
    public function addContact(): void
    {
        $this->validate();
        // $this->validate([
        //     'contact_name' => 'required|string|max:255',
        //     'contact_phone' => 'required|string|max:20',
        //     'contact_relationship' => 'nullable|string|max:100',
        //     'contact_email' => 'nullable|email|max:255',
        //     'contact_priority' => 'required|integer|min:1|max:10',
        // ]);

        EmergencyContact::create([
            'user_id' => auth()->id(),
            'name' => $this->contact_name,
            'phone' => $this->contact_phone,
            'relationship' => $this->contact_relationship,
            'email' => $this->contact_email,
            'priority' => $this->contact_priority,
            'is_active' => true,
        ]);

        $this->resetContactForm();
        $this->showContactForm = false;
    }

    /**
     * Delete emergency contact.
     */
    public function deleteContact(int $contactId): void
    {
        EmergencyContact::where('user_id', auth()->id())
            ->where('id', $contactId)
            ->delete();
    }

    /**
     * Mark emergency as resolved.
     */
    public function resolveEmergency(int $logId): void
    {
        $log = EmergencyLog::where('user_id', auth()->id())
            ->where('id', $logId)
            ->firstOrFail();

        $log->markAsResolved();
    }

    /**
     * Reset contact form.
     */
    public function resetContactForm(): void
    {
        $this->reset([
            'contact_name',
            'contact_phone',
            'contact_relationship',
            'contact_email',
            'contact_priority',
            'editingContact',
        ]);
    }
}; ?>

<!-- Emergency Contacts Section -->
<div class="bg-white rounded-lg shadow-lg p-6 mb-8">
    <div class="flex justify-between items-center mb-4">
        <h2 class="text-2xl font-bold">Emergency Contacts</h2>
        <button
            wire:click="$toggle('showContactForm')"
            class="bg-blue-500 text-white px-4 py-2 rounded hover:bg-blue-600">
            {{ $showContactForm ? 'Cancel' : 'Add Contact' }}
        </button>
    </div>

    @if($showContactForm)
    <div class="bg-gray-50 p-4 rounded-lg mb-4">
        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
            <div>
                <label class="block text-sm font-medium mb-1">Name *</label>
                <input type="text" wire:model="contact_name" class="w-full border rounded px-3 py-2" />
                @error('contact_name') <span class="text-red-500 text-sm">{{ $message }}</span> @enderror
            </div>
            <div>
                <label class="block text-sm font-medium mb-1">Phone *</label>
                <input type="text" wire:model="contact_phone" class="w-full border rounded px-3 py-2" />
                @error('contact_phone') <span class="text-red-500 text-sm">{{ $message }}</span> @enderror
            </div>
            <div>
                <label class="block text-sm font-medium mb-1">Relationship</label>
                <input type="text" wire:model="contact_relationship" class="w-full border rounded px-3 py-2" />
            </div>
            <div>
                <label class="block text-sm font-medium mb-1">Email</label>
                <input type="email" wire:model="contact_email" class="w-full border rounded px-3 py-2" />
            </div>
            <div>
                <label class="block text-sm font-medium mb-1">Priority (1 = highest)</label>
                <input type="number" wire:model="contact_priority" min="1" max="10" class="w-full border rounded px-3 py-2" />
            </div>
        </div>
        <button
            wire:click="addContact"
            class="mt-4 bg-green-500 text-white px-6 py-2 rounded hover:bg-green-600">
            Save Contact
        </button>
    </div>
    @endif

    <div class="overflow-x-auto">
        <table class="min-w-full divide-y divide-gray-200">
            <thead class="bg-gray-50">
                <tr>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Name</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Phone</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Relationship</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Priority</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Actions</th>
                </tr>
            </thead>
            <tbody class="bg-white divide-y divide-gray-200">
                @forelse($contacts as $contact)
                <tr>
                    <td class="px-6 py-4 whitespace-nowrap">{{ $contact->name }}</td>
                    <td class="px-6 py-4 whitespace-nowrap">{{ $contact->phone }}</td>
                    <td class="px-6 py-4 whitespace-nowrap">{{ $contact->relationship ?? '-' }}</td>
                    <td class="px-6 py-4 whitespace-nowrap">
                        <span class="px-2 py-1 bg-blue-100 text-blue-800 rounded">{{ $contact->priority }}</span>
                    </td>
                    <td class="px-6 py-4 whitespace-nowrap">
                        <button
                            wire:click="deleteContact({{ $contact->id }})"
                            wire:confirm="Are you sure you want to delete this contact?"
                            class="text-red-600 hover:text-red-900">
                            Delete
                        </button>
                    </td>
                </tr>
                @empty
                <tr>
                    <td colspan="5" class="px-6 py-4 text-center text-gray-500">
                        No emergency contacts added yet
                    </td>
                </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>