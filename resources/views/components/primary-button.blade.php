<button {{ $attributes->merge(['type' => 'submit', 'class' => 'tn-btn tn-btn--primary']) }}>
    {{ $slot }}
</button>
