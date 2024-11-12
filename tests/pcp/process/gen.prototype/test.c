#pragma pcp generate prototype
void fpempty();

#pragma pcp generate prototype
void fdefempty(){
    return;
}

#pragma pcp generate prototype
void fpvoid(void);

#pragma pcp generate prototype
void fpint(int a);

#pragma pcp generate prototype
void fpiint(int a, int b);

#pragma pcp generate prototype
inline int finline();

#pragma pcp generate prototype
int inline finline();

#pragma pcp generate prototype drop=inline
inline int finline();

#pragma pcp generate prototype drop=inline
int inline finline();

// ============================================================================

#pragma pcp generate prototype name.prefix=pref_
int name();

#pragma pcp generate prototype name.suffix=_suff
int name();

#pragma pcp generate prototype name.prefix=pref_ name.suffix=_suff
int name();