/** Clockwork Debugger JS **/
document.addEventListener("DOMContentLoaded", function () {
    // Directly select the script tag by its id
    var currentScript = document.getElementById('clockwork-script');

    if (!currentScript) {
        console.error("Clockwork Debugger: Script tag with id 'clockwork-script' not found.");
        return;
    }

    var route = currentScript.getAttribute('data-route') || '/clockwork'; // Default route if not specified

    // Debugging: Log the route to verify
    console.log("Clockwork Debugger Route:", route);

    // Create the badge container
    var badge = document.createElement("div");
    badge.className = "clockwork-badge";
    badge.setAttribute('aria-label', 'Clockwork Debugger Enabled');
    badge.setAttribute('role', 'button');

    // Create the icon element
    var icon = document.createElement("i");
    badge.appendChild(icon);

    // Create the tooltip element
    var tooltip = document.createElement("div");
    tooltip.className = "tooltip";
    tooltip.innerHTML = `
        <b>Grav Clockwork Debugger Enabled.</b><br>
        Install the <b>Clockwork Browser extension</b> (Chrome or Firefox) or use the <b>"Clockwork Web"</b> Grav plugin to <a href="${route}" target="_blank">View Debug Info 🔗</a>.
    `;
    badge.appendChild(tooltip);

    // Append the badge to the body
    document.body.appendChild(badge);
});