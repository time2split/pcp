#pragma pcp block ("defval") pref="def_"
#pragma pcp generate code="int ${pref}s();"
#pragma pcp block end

// Include
#pragma pcp include ("defval")
#pragma pcp include ("defval") pref="pref_"

// Plays with @config
#pragma pcp config pref="conf_"
#pragma pcp include ("defval")
/* @noconfig applies only to the 'include' action,
   not on its block's actions */
#pragma pcp include ("defval") @noconfig
#pragma pcp include ("defval") pref="pref_"
#pragma pcp include ("defval") @config pref="pref_"
#pragma pcp include ("defval") pref="pref_" @config@