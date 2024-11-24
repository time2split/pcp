#pragma pcp block ("simple")
#pragma pcp generate code="int ${pref}s();"
#pragma pcp block end

// Include
#pragma pcp include ("simple")
#pragma pcp include ("simple") pref="pref_"

// Plays with @config
#pragma pcp config pref='conf_'
#pragma pcp include ("simple")
/* @noconfig applies only to the 'include' action,
   not on its block's actions */
#pragma pcp include ("simple") @noconfig
#pragma pcp include ("simple") pref="pref_"
#pragma pcp include ("simple") @config pref="pref_"
#pragma pcp include ("simple") pref="pref_" @config@