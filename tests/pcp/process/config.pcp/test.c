#pragma pcp generate prototype
int nothing();

#pragma pcp generate prototype name.prefix="pref_"
int f();

#pragma pcp generate prototype generate.name.prefix="full_"
int f();

#pragma pcp generate prototype name.prefix="a" generate.name.prefix="b"
int f();

#pragma pcp generate prototype generate.name.prefix="b" name.prefix="a"
int f();

// ============================================================================

#pragma pcp config name.prefix="np_" x="XX"
#pragma pcp generate prototype
int c();

// The previous config is prepended
#pragma pcp config generate.name.prefix="gnp_"
#pragma pcp generate prototype
int c();
#pragma pcp generate code="int ${x}();"

// @noconfig ignore the general config
#pragma pcp config @noconfig name.prefix="direct_"
#pragma pcp generate prototype
int c();

// Unless @config is set
#pragma pcp generate prototype @noconfig @config
int cc();
#pragma pcp config @noconfig @config
#pragma pcp generate prototype
int cc();
#pragma pcp generate prototype @noconfig @config
int cc();

//== ID ==//

// Capture the current configuration
#pragma pcp config ("current")
#pragma pcp config @noconfig
#pragma pcp config ("first") name.prefix="first_"

#pragma pcp generate prototype
int nothing();

#pragma pcp generate prototype @config.current
int f();

#pragma pcp generate prototype @config.first
int f();

#pragma pcp config name.prefix="second_"
#pragma pcp generate prototype @config.first
int f();

#pragma pcp generate prototype @config @config.first
int f();

// Because @config is parent of @config.first, the order is the reverse
#pragma pcp generate prototype @config.first @config
int f();

// For this specific case use @config@ that is not a parent
#pragma pcp generate prototype @config.first @config@
int f();