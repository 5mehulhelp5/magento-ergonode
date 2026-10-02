#!/usr/bin/env perl

use strict;
use warnings;

use Digest::SHA qw(sha256_hex);
use Encode qw(decode FB_DEFAULT);
use File::Basename qw(dirname);
use File::Path qw(make_path);
use Getopt::Long qw(GetOptions);
use JSON::PP;

binmode STDOUT, ':encoding(UTF-8)';

my %options = (
    limit => 8,
    columns => 500,
);

GetOptions(
    'gate=s' => \$options{gate},
    'log=s' => \$options{log},
    'state=s' => \$options{state},
    'output=s' => \$options{output},
    'limit=i' => \$options{limit},
    'columns=i' => \$options{columns},
) or usage();

for my $required (qw(gate log state output)) {
    usage() if !defined $options{$required} || $options{$required} eq '';
}
usage() if $options{limit} < 1 || $options{columns} < 1;

my $raw = read_file($options{log});
$raw =~ s/\e\[[0-9;]*[mK]//g;
my @raw_lines = split /\R/, $raw;
my (@matched, @fallback);

for my $line (@raw_lines) {
    $line =~ s/^\s+|\s+$//g;
    $line =~ s/\s+/ /g;
    next if $line eq '' || $line =~ /^[+|=\-\s]+$/;

    $line = substr($line, 0, $options{columns});
    push @fallback, $line;
    push @matched, $line if is_diagnostic($line);
}

my @selected = @matched ? @matched : fallback_excerpt(\@fallback, $options{limit});
my (@diagnostics, %current_by_fingerprint);
for my $text (@selected) {
    my $fingerprint = fingerprint($text);
    next if exists $current_by_fingerprint{$fingerprint};

    my $diagnostic = {
        fingerprint => $fingerprint,
        text => $text,
    };
    $current_by_fingerprint{$fingerprint} = $diagnostic;
    push @diagnostics, $diagnostic;
}

my $previous = read_state($options{state});
my %previous_by_fingerprint = map {
    ($_->{fingerprint} // '') => $_
} grep {
    ref $_ eq 'HASH' && ($_->{fingerprint} // '') ne ''
} @{$previous->{diagnostics} // []};

my (@new, @unchanged);
for my $diagnostic (@diagnostics) {
    if (exists $previous_by_fingerprint{$diagnostic->{fingerprint}}) {
        push @unchanged, {%{$diagnostic}, status => 'unchanged'};
    } else {
        push @new, {%{$diagnostic}, status => 'new'};
    }
}

my @resolved = map {
    {%{$previous_by_fingerprint{$_}}, status => 'resolved'}
} grep {
    !exists $current_by_fingerprint{$_}
} sort keys %previous_by_fingerprint;

my @displayed_diagnostics;
for my $diagnostic (@new) {
    last if @displayed_diagnostics >= $options{limit};
    push @displayed_diagnostics, $diagnostic;
}

my $unchanged_displayed = 0;
for my $diagnostic (@unchanged) {
    last if @displayed_diagnostics >= $options{limit} || $unchanged_displayed >= 3;
    push @displayed_diagnostics, $diagnostic;
    $unchanged_displayed++;
}

my $resolved_preview_count = @resolved < 5 ? scalar @resolved : 5;
my @resolved_preview = $resolved_preview_count > 0
    ? @resolved[0 .. $resolved_preview_count - 1]
    : ();
my $summary = {
    gate => $options{gate},
    source_log => $options{log},
    totals => {
        raw_lines => scalar @raw_lines,
        matched_lines => scalar @selected,
        unique => scalar @diagnostics,
        new => scalar @new,
        unchanged => scalar @unchanged,
        resolved => scalar @resolved,
    },
    diagnostics => \@displayed_diagnostics,
    omitted_diagnostics => scalar(@diagnostics) - scalar(@displayed_diagnostics),
    resolved => \@resolved_preview,
    omitted_resolved => scalar(@resolved) - scalar(@resolved_preview),
};

write_json($options{output}, $summary);
write_json($options{state}, {diagnostics => \@diagnostics});

printf "DIAGNOSTICS: unique=%d new=%d unchanged=%d resolved=%d shown=%d omitted=%d\n",
    scalar @diagnostics,
    scalar @new,
    scalar @unchanged,
    scalar @resolved,
    scalar @displayed_diagnostics,
    scalar(@diagnostics) - scalar(@displayed_diagnostics);

for my $diagnostic (@displayed_diagnostics) {
    print_diagnostic($diagnostic);
}

exit 0;

sub usage {
    die "Usage: diagnostic-summary.pl --gate LABEL --log FILE --state FILE --output FILE "
        . "[--limit N] [--columns N]\n";
}

sub print_diagnostic {
    my ($diagnostic) = @_;
    my $prefix = uc($diagnostic->{status}) . ': ';
    my $available = $options{columns} - length($prefix);
    $available = 0 if $available < 0;
    printf "%s%s\n", $prefix, substr($diagnostic->{text}, 0, $available);
}

sub read_file {
    my ($path) = @_;
    open my $handle, '<:raw', $path or die "Cannot read $path: $!\n";
    local $/;
    return decode('UTF-8', <$handle> // '', FB_DEFAULT);
}

sub read_state {
    my ($path) = @_;
    return {diagnostics => []} if !-s $path;

    my $decoded = eval { JSON::PP->new->decode(read_file($path)) };
    return {diagnostics => []} if $@ || ref $decoded ne 'HASH';
    return $decoded;
}

sub write_json {
    my ($path, $data) = @_;
    my $directory = dirname($path);
    make_path($directory) if !-d $directory;

    my $temporary = "$path.tmp.$$";
    open my $handle, '>:encoding(UTF-8)', $temporary or die "Cannot write $temporary: $!\n";
    print {$handle} JSON::PP->new->canonical->pretty->encode($data);
    close $handle or die "Cannot close $temporary: $!\n";
    rename $temporary, $path or die "Cannot replace $path: $!\n";
}

sub is_diagnostic {
    my ($line) = @_;

    return 1 if $line =~ m{\bFILE:\s+\S*(?:app/code|dev/|packages/)\S*}i;
    return 1 if $line =~ m{(?:app/code|dev/|packages/)[^\s:|]+(?::\d+|\s*\|)}i;
    return 1 if $line =~ /\b(?:error|warning|fail(?:ed|ure)?|fatal|exception|invalid|missing|unknown|not found|violation|deprecated)\b/i;
    return 1 if $line =~ /^\d+\s+\S/;
    return 1 if $line =~ /(?:\[[A-Za-z][A-Za-z0-9_.-]+\]|\x{1FAAA}\s*[A-Za-z][A-Za-z0-9_.-]+)/;
    return 0;
}

sub fallback_excerpt {
    my ($lines, $limit) = @_;
    return @{$lines} if @{$lines} <= $limit;

    my $head = int($limit * 3 / 4);
    my $tail = $limit - $head;
    return (
        @{$lines}[0 .. $head - 1],
        @{$lines}[@{$lines} - $tail .. @{$lines} - 1],
    );
}

sub fingerprint {
    my ($text) = @_;
    my $canonical = lc $text;
    $canonical =~ s{\S*/((?:app/code|dev|packages)/\S+)}{$1}g;
    $canonical =~ s/:\d+(?=[:\s|])/:<line>/g;
    $canonical =~ s/^\d+(?=\s)/<line>/;
    $canonical =~ s/\s+/ /g;
    return sha256_hex($canonical);
}
