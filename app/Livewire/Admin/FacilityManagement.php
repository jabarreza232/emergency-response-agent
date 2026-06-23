<?php

namespace App\Livewire\Admin;

use Livewire\Component;
use Livewire\WithPagination;
use Livewire\Attributes\Layout;

#[Layout('layouts.app')]
class FacilityManagement extends Component
{
    use WithPagination;

    public $activeTab = 'hospital';
    public $facilityId, $name, $address, $phone, $latitude, $longitude, $type;
    public $isModalOpen = false;

    // Helper untuk memilih Model berdasarkan Tab
    private function getActiveModel()
    {
        return match($this->activeTab) {
            'police' => \App\Models\PoliceStation::class,
            'fire'   => \App\Models\FireStation::class,
            default  => \App\Models\Hospital::class,
        };
    }

    public function switchTab($tab)
    {
        $this->activeTab = $tab;
        $this->resetPage();
    }

    public function openModal($id = null)
    {
        $this->resetValidation();
        $this->reset(['name', 'address', 'phone', 'latitude', 'longitude', 'type', 'facilityId']);

        if ($id) {
            $modelClass = $this->getActiveModel();
            $facility = $modelClass::findOrFail($id);
            
            $this->facilityId = $facility->id;
            $this->name = $facility->name;
            $this->address = $facility->address ?? '';
            $this->phone = $facility->phone ?? '';
            $this->latitude = $facility->latitude;
            $this->longitude = $facility->longitude;
            
            if ($this->activeTab === 'hospital') {
                $this->type = $facility->type ?? '';
            }
        }
        
        $this->isModalOpen = true;
    }

    public function save()
    {
        $rules = [
            'name' => 'required|string|max:255',
            'latitude' => 'required|numeric',
            'longitude' => 'required|numeric',
            'phone' => 'nullable|string|max:20',
            'address' => 'nullable|string|max:255',
        ];

        if ($this->activeTab === 'hospital') {
            $rules['type'] = 'nullable|string|max:50';
        }

        $this->validate($rules);

        $data = [
            'name' => $this->name,
            'latitude' => $this->latitude,
            'longitude' => $this->longitude,
            'phone' => $this->phone,
            'address' => $this->address,
            'is_active' => true,
        ];

        if ($this->activeTab === 'hospital') {
            $data['type'] = $this->type;
        }

        $modelClass = $this->getActiveModel();
        $modelClass::updateOrCreate(['id' => $this->facilityId], $data);

        $this->isModalOpen = false;
        session()->flash('status', 'Data Fasilitas berhasil disimpan!');
    }

    public function deleteFacility($id)
    {
        $modelClass = $this->getActiveModel();
        $modelClass::findOrFail($id)->delete();
        session()->flash('status', 'Data Fasilitas berhasil dihapus!');
    }

    public function render()
    {
        $modelClass = $this->getActiveModel();
        
        return view('livewire.admin.facility-management', [
            'facilities' => $modelClass::orderBy('name', 'asc')->paginate(10)
        ]);
    }
}