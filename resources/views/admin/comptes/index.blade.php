<x-app-layout>
    <x-slot name="header">
        <h1 class="font-display font-bold text-2xl sm:text-3xl text-gray-900 leading-tight">Comptes</h1>
    </x-slot>

    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-6">
            @include('admin._subnav')

            @error('role')
                <div class="rounded-md bg-red-50 border border-red-200 p-4 text-sm text-red-800" role="alert">{{ $message }}</div>
            @enderror

            <div class="bg-white overflow-hidden border border-gray-200 rounded-xl">
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200 text-sm">
                        <thead class="bg-gray-50 text-left text-gray-500">
                            <tr>
                                <th class="px-6 py-3 font-medium">Nom</th>
                                <th class="px-6 py-3 font-medium">Adresse e-mail</th>
                                <th class="px-6 py-3 font-medium">Rôle</th>
                                <th class="px-6 py-3 font-medium">Changer le rôle</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100">
                            @foreach ($users as $user)
                                <tr>
                                    <td class="px-6 py-3">{{ $user->name }}</td>
                                    <td class="px-6 py-3">{{ $user->email }}</td>
                                    <td class="px-6 py-3">
                                        <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-indigo-100 text-indigo-800">{{ $user->role->label() }}</span>
                                    </td>
                                    <td class="px-6 py-3">
                                        <form method="POST" action="{{ route('admin.comptes.role', $user) }}" class="flex items-center gap-2">
                                            @csrf
                                            <select name="role" class="border-gray-300 rounded-md shadow-sm text-sm py-1" aria-label="Rôle de {{ $user->name }}">
                                                @foreach ($roles as $role)
                                                    <option value="{{ $role->value }}" @selected($user->role === $role)>{{ $role->label() }}</option>
                                                @endforeach
                                            </select>
                                            <x-secondary-button type="submit">Enregistrer</x-secondary-button>
                                        </form>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
