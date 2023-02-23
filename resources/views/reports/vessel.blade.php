<!DOCTYPE html>
<html>

<head>
    <style>
        .col-right {
            text-align: right;
        }

        .col-middle {
            text-align: center;
        }

        .col-middle img {
            width: 20px;
        }
    </style>
</head>

<body>
    <table>
        <thead>
            <tr>
                <th>
                    <div class="col-left">
                        <h1>Drills and Exercises Report</h1>
                    </div>
                </th>
                <th>
                    <div class="col-middle">
                        <img src="{{ public_path('images/DJS.png') }}" />
                    </div>
                </th>
                <th>
                    <div class="col-right">
                        <h1>Created on : {{ date('d-m-y') }}</h1>
                    </div>
                </th>
            </tr>
        </thead>
    </table>
    <table>
        <thead>
            <tr>
                <th>Company</th>
                <th>Vessel</th>
                <th>File</th>
                <th>Size</th>
                <th>Created</th>
                <th>Updated</th>
            </tr>
        </thead>
        <tbody>
            @for ($i = 0; $i < count($companyVesselFileInformations) - 1; $i++)
            @for ($j = 0; $j < count($companyVesselFileInformations[$i]['files']); $j++)
            <tr>
                @if($i===0 && $j===0)
                <td>{{ $companyVesselFileInformations['company_name'] }}</td>
                @else
                <td></td>
                @endif
                @if($j===0)
                <td>{{ $companyVesselFileInformations[$i]['vessel_name'] }}</td>
                @else
                <td></td>
                @endif
                <td>{{ $companyVesselFileInformations[$i]['files'][$j]['name'] }}</td>
                <td>{{ $companyVesselFileInformations[$i]['files'][$j]['size'] }}</td>
                <td>{{ $companyVesselFileInformations[$i]['files'][$j]['created_at'] }}</td>
                <td>{{ $companyVesselFileInformations[$i]['files'][$j]['created_at'] }}</td>
            </tr>
            @endfor
            @endfor
        </tbody>
    </table>
</body>

</html>