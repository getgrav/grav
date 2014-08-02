// NetBeans
// var editor = '"C:\\Program Files\\NetBeans 6.9.1\\bin\\netbeans.exe" "%file%:%line%" --console suppress';

// PhpStorm
// var editor = '"C:\\Users\\Martin\\AppData\\Roaming\\JetBrains\\PhpStorm 7.1.1\\bin\\PhpStorm.exe" --line %line% "%file%"';

// Nusphere PHPEd
// var editor = '"C:\\Program Files\\NuSphere\\PhpED\\phped.exe" "%file%" --line=%line%';

// SciTE
// var editor = '"C:\\Program Files\\SciTE\\scite.exe" "-open:%file%" -goto:%line%';

// EmEditor
// var editor = '"C:\\Program Files\\EmEditor\\EmEditor.exe" "%file%" /l %line%';

// PSPad Editor
// var editor = '"C:\\Program Files\\PSPad editor\\PSPad.exe" -%line% "%file%"';

// gVim
// var editor = '"C:\\Program Files\\Vim\\vim73\\gvim.exe" "%file%" +%line%';

// Sublime Text 2
// var editor = '"C:\\Program Files\\Sublime Text 2\\sublime_text.exe" "%file%:%line%"';

var mappings = {
	// '/remotepath': '/localpath'
};

if (typeof editor === 'undefined') {
	WScript.Echo('Create variable "editor" in ' + WScript.ScriptFullName);
	WScript.Quit();
}

var url = WScript.Arguments(0);
var match = /^editor:\/\/open\/\?file=(.+)&line=(\d+)/.exec(url);
if (match) {
	var file = decodeURIComponent(match[1]).replace(/\+/g, ' ');
	for (var id in mappings) {
		if (file.indexOf(id) === 0) {
			file = mappings[id] + file.substr(id.length);
			break;
		}
	}
	var command = editor.replace(/%line%/g, match[2]).replace(/%file%/g, file);
	var shell = new ActiveXObject("WScript.Shell");
	shell.Exec(command.replace(/\\/g, '\\\\'));
}
