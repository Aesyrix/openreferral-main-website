<x-layouts.app title="App Content">
    <x-ui.page-header
        title="App Content"
        subtitle="About, Privacy, Terms and Help copy served to the mobile app — edits go live instantly, no app release needed." />

    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(280px, 1fr)); gap: 16px;">
        @foreach($slugs as $slug)
            @php($page = $pages[$slug] ?? null)
            <div class="or-card" style="display: flex; flex-direction: column;">
                <div style="display: flex; justify-content: space-between; align-items: flex-start; gap: 10px;">
                    <div>
                        <h2 style="font-size: 16px; font-weight: 700; margin: 0;">{{ ucfirst($slug) }}</h2>
                        <div style="font-size: 12px; color: var(--or-slate); margin-top: 3px;">
                            <code>GET /api/content/{{ $slug }}</code>
                        </div>
                    </div>
                    <span class="or-badge {{ $page ? 'badge-green' : 'badge-amber' }}">{{ $page ? 'Published' : 'Not set' }}</span>
                </div>

                @if($page)
                    <div style="margin-top: 12px; font-size: 13px;">
                        <div style="font-weight: 600;">{{ $page->title }}</div>
                        @if($page->intro)
                            <p style="color: var(--or-slate); margin: 5px 0 0; line-height: 1.6;">{{ Str::limit($page->intro, 130) }}</p>
                        @endif
                        <div style="font-size: 12px; color: var(--or-slate); margin-top: 8px;">
                            {{ count($page->sections()) }} section(s) · updated {{ $page->updated_at?->diffForHumans() }}
                        </div>
                    </div>
                @else
                    <p style="font-size: 13px; color: var(--or-slate); margin: 12px 0 0; line-height: 1.6;">
                        Nothing published yet — the app falls back to its bundled copy until you add content here.
                    </p>
                @endif

                <div style="margin-top: auto; padding-top: 14px;">
                    <a href="{{ route('admin.content.edit', $slug) }}" class="or-btn or-btn-primary" style="width: 100%;">
                        {{ $page ? 'Edit content' : 'Add content' }}
                    </a>
                </div>
            </div>
        @endforeach
    </div>
</x-layouts.app>
