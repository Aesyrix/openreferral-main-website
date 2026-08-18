<x-layouts.app title="Edit {{ ucfirst($slug) }}">
    <x-ui.page-header
        :title="'Edit '.ucfirst($slug).' page'"
        subtitle="Sections render in order inside the app. Keep headings short.">
        <x-slot:actions>
            <a href="{{ route('admin.content.index') }}" class="or-btn or-btn-secondary">← All content</a>
        </x-slot:actions>
    </x-ui.page-header>

    @php($sections = old('sections', $page?->sections() ?: [['heading' => '', 'body' => '']]))

    <form method="POST" action="{{ route('admin.content.update', $slug) }}" style="max-width: 780px;" id="contentForm">
        @csrf @method('PUT')

        <div class="or-card" style="margin-bottom: 18px;">
            <x-ui.field label="Page title" name="title" :value="$page?->title" required
                        :placeholder="'About '.\App\Models\Setting::get('company_name')" />
            <x-ui.field label="Intro (optional)" name="intro" type="textarea" :rows="2" :value="$page?->intro"
                        hint="One or two lines shown above the sections." />
        </div>

        <div id="sections">
            @foreach($sections as $index => $section)
                <div class="or-card content-section" style="margin-bottom: 14px;">
                    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 10px;">
                        <span style="font-size: 11px; font-weight: 700; text-transform: uppercase; letter-spacing: 0.5px; color: var(--or-slate);">
                            Section {{ $index + 1 }}
                        </span>
                        <button type="button" class="or-btn or-btn-secondary remove-section" style="padding: 4px 10px; font-size: 11px;">Remove</button>
                    </div>

                    <label style="display: block; font-size: 12px; font-weight: 700; color: #334155; margin-bottom: 5px;">Heading</label>
                    <input type="text" name="sections[{{ $index }}][heading]" value="{{ $section['heading'] ?? '' }}" required maxlength="190"
                           style="width: 100%; padding: 9px 12px; border: 1px solid #CBD5E1; border-radius: 8px; font-size: 14px; box-sizing: border-box; margin-bottom: 12px;">

                    <label style="display: block; font-size: 12px; font-weight: 700; color: #334155; margin-bottom: 5px;">Body</label>
                    <textarea name="sections[{{ $index }}][body]" rows="4" required
                              style="width: 100%; padding: 9px 12px; border: 1px solid #CBD5E1; border-radius: 8px; font-size: 14px; box-sizing: border-box; font-family: inherit; resize: vertical;">{{ $section['body'] ?? '' }}</textarea>
                </div>
            @endforeach
        </div>

        <div style="display: flex; gap: 10px; margin-top: 18px; flex-wrap: wrap;">
            <button type="button" id="addSection" class="or-btn or-btn-secondary">+ Add section</button>
            <button type="submit" class="or-btn or-btn-primary" style="padding: 11px 26px;">Save & publish</button>
            <a href="{{ route('admin.content.index') }}" class="or-btn or-btn-secondary" style="padding: 11px 26px;">Cancel</a>
        </div>
    </form>

    <script>
        (function () {
            const container = document.getElementById('sections');

            function renumber() {
                container.querySelectorAll('.content-section').forEach((section, index) => {
                    section.querySelector('span').textContent = 'Section ' + (index + 1);
                    section.querySelector('input[type=text]').name = `sections[${index}][heading]`;
                    section.querySelector('textarea').name = `sections[${index}][body]`;
                });
            }

            document.getElementById('addSection').addEventListener('click', () => {
                const first = container.querySelector('.content-section');
                const clone = first.cloneNode(true);
                clone.querySelector('input[type=text]').value = '';
                clone.querySelector('textarea').value = '';
                container.appendChild(clone);
                renumber();
            });

            container.addEventListener('click', (event) => {
                if (! event.target.classList.contains('remove-section')) return;
                if (container.querySelectorAll('.content-section').length === 1) {
                    alert('Keep at least one section.');
                    return;
                }
                event.target.closest('.content-section').remove();
                renumber();
            });
        })();
    </script>
</x-layouts.app>
