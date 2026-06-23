<?php

namespace App\Livewire\Admin;

use Livewire\Component;
use Livewire\WithPagination;
use Livewire\Attributes\Layout;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;

#[Layout('layouts.app')]
class UserManagement extends Component
{
    use WithPagination;

    public $userId, $name, $email, $password;
    public $isModalOpen = false;

    public function openModal($id = null)
    {
        $this->resetValidation();
        $this->reset(['name', 'email', 'password', 'userId']);

        if ($id) {
            $user = User::findOrFail($id);
            $this->userId = $user->id;
            $this->name = $user->name;
            $this->email = $user->email;
        }
    }

    public function save()
    {
        $this->validate([
            'name' => 'required|string|max:255',
            'email' => ['required', 'email', Rule::unique('users')->ignore($this->userId)],
            'password' => $this->userId ? 'nullable|min:6' : 'required|min:6',
        ]);

        // Cari user lama jika edit, atau buat instance baru jika tambah
        $user = User::findOrNew($this->userId);
        $user->name = $this->name;
        $user->email = $this->email;

        // SET DEFAULT ROLE_ID = 2 (Warga) HANYA UNTUK USER BARU
        if (!$this->userId) {
            $user->role_id = 2;
        }

        // Update password jika ada inputan (wajib saat tambah, opsional saat edit)
        if ($this->password) {
            $user->password = Hash::make($this->password);
        }

        $user->save();

        $this->isModalOpen = false;
        session()->flash('status', $this->userId ? 'Data Warga berhasil diperbarui!' : 'Warga baru berhasil ditambahkan!');
    }

    public function deleteUser($id)
    {
        User::findOrFail($id)->delete();
        session()->flash('status', 'Data Warga berhasil dihapus!');
    }

    public function render()
    {
        return view('livewire.admin.user-management', [
            // Hanya tampilkan data yang role_id-nya bukan 1 (bukan Admin)
            'users' => User::where('role_id', '!=', 1)->latest()->paginate(10)
        ]);
    }
}