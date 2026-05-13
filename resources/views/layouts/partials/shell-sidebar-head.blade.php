{{-- Runs before paint + Alpine: matches desktop sidebar / coordinator workspace state from localStorage to prevent FOUC flash on navigation and clicks. --}}
@props([
    'collapseKey' => null,
    'workspaceFocusKey' => null,
])
@if ($collapseKey || $workspaceFocusKey)
<script>
(function () {
    try {
        @if ($collapseKey)
        if (localStorage.getItem(@json($collapseKey)) === '1') {
            document.documentElement.classList.add('qs-shell-sidebar-collapsed');
        }
        @endif
        @if ($workspaceFocusKey)
        if (localStorage.getItem(@json($workspaceFocusKey)) === '1') {
            document.documentElement.classList.add('qs-shell-workspace-focus');
        }
        @endif
    } catch (e) {}
})();
</script>
@endif
