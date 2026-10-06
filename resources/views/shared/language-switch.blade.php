<form method="post" action="{{ route('language.update') }}" class="d-flex align-items-center gap-1" aria-label="{{ __('Language') }}">@csrf
<input type="hidden" name="return_to" value="{{ request()->getRequestUri() }}">
<button class="btn btn-sm {{ app()->getLocale()==='bn' ? 'btn-primary':'btn-outline-secondary' }}" name="locale" value="bn" type="submit" lang="bn">বাংলা</button>
<button class="btn btn-sm {{ app()->getLocale()==='en' ? 'btn-primary':'btn-outline-secondary' }}" name="locale" value="en" type="submit" lang="en">English</button>
</form>
