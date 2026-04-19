<div x-data="{ isDark: document.documentElement.classList.contains('dark') }"
     x-init="
        new MutationObserver(() => { isDark = document.documentElement.classList.contains('dark') })
            .observe(document.documentElement, { attributes: true, attributeFilter: ['class'] })
     "
     class="flex items-center justify-center align-center">

    <img
        src="{{ asset('brand-logo.webp') }}"
        alt="Marcelino's Logo"
        style="height: 45px; width: auto;"
        loading="lazy"
    >
        <div class="ml-1 leading-tight w-fit text-shadow-[0_0.5px_1px_rgba(0.9,0.9,0.9,0.9)] ">
            <div 
            class="text-sm font-serif tracking-[0.13rem] whitespace-nowrap text-center"
            :class="isDark ? 'text-[#fff]' : 'text-[#456e26]'">
                MARCELINO'S
            </div>

            <div 
            class="text-xs tracking-[0.2rem] text-center"
            :class="isDark ? 'text-[#E6D3A3]' : 'text-[#E6D3A3]'">
                RESORT HOTEL
            </div>
        </div>

</div>