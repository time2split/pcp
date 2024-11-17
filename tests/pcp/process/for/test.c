void fdefempty0()
{
    return;
}

#pragma pcp for cond=${tags:"from.function"}
#pragma pcp generate prototype
#pragma pcp for end

void fempty();

int fdefint(void)
{
    return 0;
}

#pragma pcp for clear

void fdefempty1()
{
    return;
}
