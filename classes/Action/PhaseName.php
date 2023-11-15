<?php
namespace Action;

enum PhaseName{
    case ReadingOneFile;
    case OpeningDirectory;
    case ProcessingFiles;
}