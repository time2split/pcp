#pragma pcp block ("noconf")
#pragma pcp generate code="int ${pref}s();" @noconfig
#pragma pcp block end

// Include
#pragma pcp include ("noconf")
#pragma pcp include ("noconf") pref="pref_"

// Plays with @config
#pragma pcp config pref="conf_"
#pragma pcp include ("noconf")
/* @noconfig applies only to the 'include' action,
   not on its block's actions */
#pragma pcp include ("noconf") @noconfig
#pragma pcp include ("noconf") pref="pref_"
#pragma pcp include ("noconf") @config pref="pref_"
#pragma pcp include ("noconf") pref="pref_" @config@